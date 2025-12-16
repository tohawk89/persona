# Persona Gallery UI Specification

Purpose
- Provide a responsive, accessible gallery UI for viewing all media attached to a `Persona` via Spatie MediaLibrary collections: `reference_image`, `avatar`, `generated_images`, `voice_notes`.
- No database schema changes.

High-level layout
- Page or modal that mounts a Livewire component `PersonaGallery`.
- Responsive masonry-like grid: 3 columns (desktop), 2 (tablet), 1 (mobile).
- Top bar: back link, persona name, collection filter dropdown (All / Avatar / Reference / Generated / Voice), search, sort.

Item card
- Thumbnail (image or waveform/placeholder for audio)
- Small overlay icon for media type (📷, 🎤)
- Metadata: collection label, created_at
- Controls: preview (open modal), download

Preview modal
- Large image or audio player (HTML5 `<audio>` with controls)
- Metadata panel: filename, size, uploaded by (if available), created_at, collection
- Actions: download (signed URL), copy link, close
- Keyboard: Escape closes modal, left/right arrow to navigate
- Focus trap in modal and ARIA labels

UX details
- Lazy-load images (loading="lazy")
- Images should use MediaLibrary conversions for thumbnails (`thumb`) and previews (`large`) — implemented in model conversions later
- Paginate server-side (Livewire) or client-side with incremental loading
- For private storage, use signed URLs from the server for preview/download

Livewire contract (component responsibilities)
- Props: `public Persona $persona; public string $collection = 'all'; public ?string $search = null; public string $sort = 'newest'; public int $perPage = 24;`
- Methods: `loadMedia()`, `previewMedia($mediaId)`, `downloadMedia($mediaId)`, `loadMore()`
- Emits: `open-preview` with media payload
- Events: listens for `media-updated` to refresh when jobs attach new media

Acceptance criteria
- Media displays grouped/filtered by collection and paginated
- Preview modal shows image/audio and metadata
- Downloads use signed URL if storage is private
- No DB migrations required

Accessibility
- All interactive elements keyboard-focusable
- Modal focus management and ARIA roles
- Images include alt text where available

Example Blade usage

```blade
<!-- Mount the Livewire component in a persona page -->
<livewire:persona-gallery :persona="$persona" />
```

Example grid card (simplified):

```blade
<div class="relative bg-gray-50 rounded overflow-hidden">
  <img src="{{ $media->getUrl('thumb') }}" alt="{{ $media->name }}" loading="lazy" class="w-full h-48 object-cover">
  <div class="absolute top-2 right-2 flex gap-2">
    <button wire:click="previewMedia({{ $media->id }})" aria-label="Preview">
      <!-- icon -->
    </button>
    <a href="{{ route('persona.media.download', [$persona, $media->id]) }}" aria-label="Download">
      <!-- download icon -->
    </a>
  </div>
  <div class="p-2 text-xs text-gray-600">
    <div>{{ $media->collection_name }}</div>
    <div class="text-[10px]">{{ $media->created_at->diffForHumans() }}</div>
  </div>
</div>
```

Notes for implementation
- Add `registerMediaConversions()` in `App\Models\Persona` to create `thumb` and `large` conversions.
- Use `Storage::temporaryUrl()` or MediaLibrary's `getTemporaryUrl()` when storage is private.
- Emphasize client performance: CDN, caching headers, and lazy loading.

Next: I can scaffold the Livewire component and the example Blade file for a visual preview.```
