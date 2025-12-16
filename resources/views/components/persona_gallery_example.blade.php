@props(['persona'])

<div class="max-w-7xl mx-auto p-4">
  <header class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-semibold">{{ $persona->name }} — Gallery</h1>
      <p class="text-sm text-gray-500">Viewing media for this persona</p>
    </div>
    <div class="flex items-center gap-2">
      <select class="border rounded px-2 py-1 text-sm">
        <option>All</option>
        <option>Avatar</option>
        <option>Reference</option>
        <option>Generated</option>
        <option>Voice</option>
      </select>
      <input placeholder="Search" class="border rounded px-2 py-1 text-sm" />
    </div>
  </header>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($persona->getMedia('generated_images') as $media)
      <div class="relative bg-white rounded shadow overflow-hidden">
        <img src="{{ $media->getUrl('thumb') }}" alt="{{ $media->name }}" loading="lazy" class="w-full h-48 object-cover">

        <div class="absolute inset-0 flex items-start justify-end p-2 pointer-events-none">
          <div class="bg-black/50 text-white rounded p-1 text-xs pointer-events-auto">
            {{ ucfirst($media->collection_name) }}
          </div>
        </div>

        <div class="p-3 flex items-center justify-between">
          <div class="text-xs text-gray-600">{{ $media->created_at->format('Y-m-d') }}</div>
          <div class="flex items-center gap-2">
            <button class="text-sm text-blue-600" @click.prevent="$dispatch('open-preview', {id: {{ $media->id }}})">Preview</button>
            <a class="text-sm text-gray-700" href="{{ route('persona.media.download', [$persona, $media->id]) }}">Download</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <!-- Preview modal (simplified, can be Alpine-managed) -->
  <div id="previewModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg overflow-hidden max-w-3xl w-full">
      <div class="p-4">
        <h2 class="font-semibold">Preview</h2>
        <div id="previewContent" class="mt-4">
          <!-- Image or audio player inserted here by Livewire/Alpine -->
        </div>
      </div>
      <div class="p-4 text-right">
        <button class="bg-gray-200 rounded px-3 py-1" onclick="document.getElementById('previewModal').classList.add('hidden')">Close</button>
      </div>
    </div>
  </div>
</div>