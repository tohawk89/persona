<?php

namespace App\Livewire;

use App\Models\Persona;
use App\Services\ImageGeneratorManager;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class PersonaAvatarEditor extends Component
{
    use WithFileUploads;

    public Persona $persona;
    public string $mode = 'upload'; // 'upload' or 'generate'
    public string $gender = 'male';
    public ?string $description = null;
    public $photo = null;
    public bool $isProcessing = false;

    private const PASSPORT_PROMPT = 'Professional passport photograph headshot, neutral expression, plain off-white background, even studio lighting, sharp focus, photorealistic.';

    protected $rules = [
        'photo' => 'nullable|image|max:10240', // 10MB max
        'gender' => 'required|in:male,female,non-binary',
        'description' => 'nullable|string|max:500',
    ];

    public function mount(Persona $persona): void
    {
        $this->persona = $persona;
        
        // Set default gender from persona if available
        if ($persona->gender) {
            $this->gender = $persona->gender;
        }
    }

    public function updatedMode(): void
    {
        // Reset file upload when switching modes
        $this->photo = null;
        $this->reset(['description']);
    }

    public function generateAvatar(): void
    {
        $this->validate([
            'gender' => 'required|in:male,female,non-binary',
            'description' => 'nullable|string|max:500',
        ]);

        $this->isProcessing = true;

        try {
            // Construct prompt
            $genderText = $this->gender;
            $descriptionText = $this->description ? " {$this->description}." : '';
            $fullPrompt = "A {$genderText}{$descriptionText}. " . self::PASSPORT_PROMPT;

            Log::info('PersonaAvatarEditor: Generating avatar from text', [
                'persona_id' => $this->persona->id,
                'prompt' => $fullPrompt,
            ]);

            // Use KieAi Text-to-Image driver
            $imageGenerator = app(ImageGeneratorManager::class)->driver('kie_ai_text_to_image');
            $imageUrl = $imageGenerator->generate($fullPrompt, $this->persona);

            if (!$imageUrl) {
                $this->dispatch('error', message: 'Failed to generate avatar. Please try again.');
                Log::error('PersonaAvatarEditor: Generation failed - empty URL returned');
                return;
            }

            // Save to avatar collection
            $this->saveAvatarFromUrl($imageUrl);

            $this->dispatch('success', message: 'Passport photo generated successfully!');
            $this->dispatch('avatar-updated');

        } catch (\Exception $e) {
            Log::error('PersonaAvatarEditor: Avatar generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('error', message: 'Adoi, ada masalah sikit... Failed to generate avatar.');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function uploadReference(): void
    {
        $this->validate([
            'photo' => 'required|image|max:10240',
        ]);

        $this->isProcessing = true;

        try {
            Log::info('PersonaAvatarEditor: Processing uploaded reference photo', [
                'persona_id' => $this->persona->id,
                'filename' => $this->photo->getClientOriginalName(),
            ]);

            // Save photo to reference_image collection (replaces existing due to singleFile())
            $media = $this->persona->addMedia($this->photo->getRealPath())
                ->usingFileName($this->photo->getClientOriginalName())
                ->toMediaCollection('reference_image');

            $refUrl = $media->getUrl();

            Log::info('PersonaAvatarEditor: Reference image saved', [
                'url' => $refUrl,
                'media_id' => $media->id,
            ]);

            // Use KieAi Edit driver to transform into passport photo
            $imageGenerator = app(ImageGeneratorManager::class)->driver('kie_ai_edit');
            $avatarUrl = $imageGenerator->editImage($refUrl, self::PASSPORT_PROMPT, $this->persona);

            if (!$avatarUrl) {
                $this->dispatch('error', message: 'Failed to process photo. Please try again.');
                Log::error('PersonaAvatarEditor: Edit failed - empty URL returned');
                return;
            }

            // Save to avatar collection
            $this->saveAvatarFromUrl($avatarUrl);

            $this->dispatch('success', message: 'Passport photo created from your upload!');
            $this->dispatch('avatar-updated');
            $this->photo = null;

        } catch (\Exception $e) {
            Log::error('PersonaAvatarEditor: Upload processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('error', message: 'Adoi, ada masalah sikit... Failed to process photo.');
        } finally {
            $this->isProcessing = false;
        }
    }

    private function saveAvatarFromUrl(string $url): void
    {
        // Download and save to avatar collection (replaces existing due to singleFile())
        $this->persona->addMediaFromUrl($url)
            ->toMediaCollection('avatar');

        Log::info('PersonaAvatarEditor: Avatar saved to collection', [
            'persona_id' => $this->persona->id,
            'url' => $url,
        ]);
    }

    public function render()
    {
        return view('livewire.persona-avatar-editor')
            ->layout('layouts.app');
    }
}
