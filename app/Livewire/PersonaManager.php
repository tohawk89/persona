<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Persona;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PersonaManager extends Component
{
    use WithFileUploads;

    public $name;
    public $about_description;
    public $system_prompt;
    public $appearance_description;
    public $physical_traits;
    public $wake_time;
    public $sleep_time;
    public $voice_frequency;
    public $image_frequency;
    public $is_active = true;
    public $new_photos = [];
    public $accumulated_photos = [];
    public ?Persona $persona = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'about_description' => 'nullable|string',
        'system_prompt' => 'required|string|min:10',
        'appearance_description' => 'nullable|string',
        'physical_traits' => 'nullable|string',
        'wake_time' => 'required|date_format:H:i',
        'sleep_time' => 'required|date_format:H:i',
        'voice_frequency' => 'required|in:never,rare,moderate,frequent',
        'image_frequency' => 'required|in:never,rare,moderate,frequent',
        'is_active' => 'boolean',
        'new_photos.*' => 'nullable|image|max:10240', // 10MB max per image
    ];

    public function mount()
    {
        $this->persona = Auth::user()->persona;

        if ($this->persona) {
            $this->name = $this->persona->name;
            $this->about_description = $this->persona->about_description;
            $this->system_prompt = $this->persona->system_prompt;
            $this->appearance_description = $this->persona->appearance_description;
            $this->physical_traits = $this->persona->physical_traits;
            // Convert HH:MM:SS to HH:MM for time input
            $this->wake_time = substr($this->persona->wake_time, 0, 5);
            $this->sleep_time = substr($this->persona->sleep_time, 0, 5);
            $this->voice_frequency = $this->persona->voice_frequency ?? 'moderate';
            $this->image_frequency = $this->persona->image_frequency ?? 'moderate';
            $this->is_active = $this->persona->is_active;
        } else {
            // Set defaults
            $this->wake_time = '07:00';
            $this->sleep_time = '23:00';
            $this->voice_frequency = 'moderate';
            $this->image_frequency = 'moderate';
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
            'about_description' => $this->about_description,
            'system_prompt' => $this->system_prompt,
            'appearance_description' => $this->appearance_description,
            'physical_traits' => $this->physical_traits,
            'wake_time' => $this->wake_time,
            'sleep_time' => $this->sleep_time,
            'voice_frequency' => $this->voice_frequency,
            'image_frequency' => $this->image_frequency,
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

    public function optimizeSystemPrompt()
    {
        if (empty($this->about_description)) {
            session()->flash('error', 'Please enter a personality concept first.');
            return;
        }

        try {
            $optimizationPrompt = <<<PROMPT
You are an expert AI prompt engineer. Transform the following raw personality description into a professional System Instruction for an AI companion.

RAW DESCRIPTION:
{$this->about_description}

TASK:
1. Expand this into a detailed, structured system prompt
2. Include personality traits, communication style, behavioral guidelines
3. Make it clear, actionable, and comprehensive
4. Keep the core personality intact while adding professional structure
5. Format it as a direct instruction to the AI (use "You are..." format)

Output ONLY the optimized system prompt, no explanations or meta-commentary.
PROMPT;

            $response = \App\Facades\GeminiBrain::callGemini($optimizationPrompt);
            $this->system_prompt = trim($response);
            
            session()->flash('success', 'System prompt optimized successfully!');
        } catch (\Exception $e) {
            Log::error('PersonaManager: Failed to optimize system prompt', [
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to optimize prompt. Please try again.');
        }
    }

    public function optimizePhysicalTraits()
    {
        if (empty($this->appearance_description)) {
            session()->flash('error', 'Please enter an appearance concept first.');
            return;
        }

        try {
            $optimizationPrompt = <<<PROMPT
You are an expert at writing photorealistic image generation prompts. Transform the following raw appearance description into a professional prompt for AI image generation.

RAW DESCRIPTION:
{$this->appearance_description}

TASK:
1. Expand this into a detailed physical description suitable for image generation
2. Include: facial features, hair style/color, eye color, body type, skin tone, typical style/fashion
3. Use clear, descriptive language that works well with image AI models
4. Keep it concise but comprehensive (2-4 sentences)
5. Focus on consistent, defining physical characteristics

Output ONLY the optimized physical traits description, no explanations or meta-commentary.
PROMPT;

            $response = \App\Facades\GeminiBrain::callGemini($optimizationPrompt);
            $this->physical_traits = trim($response);
            
            session()->flash('success', 'Physical traits optimized successfully!');
        } catch (\Exception $e) {
            Log::error('PersonaManager: Failed to optimize physical traits', [
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to optimize traits. Please try again.');
        }
    }

    public function render()
    {
        return view('livewire.persona-manager')
            ->layout('layouts.app');
    }
}
