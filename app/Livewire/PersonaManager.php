<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Persona;
use Illuminate\Support\Facades\Auth;

class PersonaManager extends Component
{
    use WithFileUploads;

    public $name;
    public $system_prompt;
    public $physical_traits;
    public $wake_time;
    public $sleep_time;
    public $is_active = true;
    public $new_photos = [];
    public $accumulated_photos = [];
    public ?Persona $persona = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'system_prompt' => 'required|string|min:10',
        'physical_traits' => 'nullable|string',
        'wake_time' => 'required|date_format:H:i',
        'sleep_time' => 'required|date_format:H:i',
        'is_active' => 'boolean',
        'new_photos.*' => 'nullable|image|max:10240', // 10MB max per image
    ];

    public function mount()
    {
        $this->persona = Auth::user()->persona;

        if ($this->persona) {
            $this->name = $this->persona->name;
            $this->system_prompt = $this->persona->system_prompt;
            $this->physical_traits = $this->persona->physical_traits;
            // Convert HH:MM:SS to HH:MM for time input
            $this->wake_time = substr($this->persona->wake_time, 0, 5);
            $this->sleep_time = substr($this->persona->sleep_time, 0, 5);
            $this->is_active = $this->persona->is_active;
        } else {
            // Set defaults
            $this->wake_time = '07:00';
            $this->sleep_time = '23:00';
            $this->is_active = true;
        }
    }

    public function updatedNewPhotos()
    {
        $this->validate([
            'new_photos.*' => 'image|max:10240',
        ]);

        // Accumulate photos instead of replacing
        if (!empty($this->new_photos)) {
            foreach ($this->new_photos as $photo) {
                $this->accumulated_photos[] = $photo;
            }
            $this->new_photos = [];
        }
    }

    public function removeAccumulatedPhoto($index)
    {
        unset($this->accumulated_photos[$index]);
        $this->accumulated_photos = array_values($this->accumulated_photos);
    }

    public function save()
    {
        $this->validate();

        $user = Auth::user();

        $data = [
            'name' => $this->name,
            'system_prompt' => $this->system_prompt,
            'physical_traits' => $this->physical_traits,
            'wake_time' => $this->wake_time,
            'sleep_time' => $this->sleep_time,
            'is_active' => $this->is_active,
        ];

        if ($this->persona) {
            $this->persona->update($data);
        } else {
            $data['user_id'] = $user->id;
            $this->persona = Persona::create($data);
        }

        // Handle multiple reference images upload
        if (!empty($this->accumulated_photos)) {
            foreach ($this->accumulated_photos as $photo) {
                $this->persona->addMedia($photo->getRealPath())
                    ->usingFileName($photo->getClientOriginalName())
                    ->toMediaCollection('reference_images');
            }
            $this->accumulated_photos = [];
        }

        // Refresh persona to load media
        $this->persona = $this->persona->fresh();

        session()->flash('success', 'Persona saved successfully!');
    }

    public function deleteMedia($mediaId)
    {
        if ($this->persona) {
            $media = $this->persona->getMedia('reference_images')->find($mediaId);
            if ($media) {
                $media->delete();
                session()->flash('success', 'Image deleted successfully!');
            }
        }
    }

    public function render()
    {
        return view('livewire.persona-manager')
            ->layout('layouts.app');
    }
}
