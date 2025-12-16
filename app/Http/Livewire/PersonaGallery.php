<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Persona;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PersonaGallery extends Component
{
    use WithPagination;

    public Persona $persona;
    public string $collection = 'all';
    public ?int $previewMediaId = null;
    public int $perPage = 24;

    public function mount(Persona $persona)
    {
        // Authorization check
        if ($persona->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to persona.');
        }

        $this->persona = $persona;
    }

    public function updatedCollection()
    {
        $this->resetPage();
    }

    public function previewMedia($mediaId)
    {
        // Validate media belongs to this persona
        $media = Media::find($mediaId);

        if (!$media || $media->model_id !== $this->persona->id || $media->model_type !== Persona::class) {
            $this->dispatch('show-error', message: 'Invalid media selected.');
            return;
        }

        $this->previewMediaId = $mediaId;
    }

    public function closePreview()
    {
        $this->previewMediaId = null;
    }

    public function render()
    {
        $query = $this->persona->media();

        if ($this->collection !== 'all') {
            $query->where('collection_name', $this->collection);
        }

        $mediaItems = $query->latest()->paginate($this->perPage);

        $previewMedia = $this->previewMediaId
            ? Media::find($this->previewMediaId)
            : null;

        return view('livewire.persona-gallery', [
            'mediaItems' => $mediaItems,
            'previewMedia' => $previewMedia,
        ])->layout('layouts.app');
    }
}
