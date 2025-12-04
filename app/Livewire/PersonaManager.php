<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Persona;
use App\Models\MemoryTag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Gemini;

class PersonaManager extends Component
{
    use WithFileUploads;

    public $name;
    public $about_description;
    public $system_prompt;
    public $appearance_description;
    public $physical_traits;
    public $gender;
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
        'gender' => 'required|in:male,female,non-binary,other',
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
            $this->gender = $this->persona->gender ?? 'female';
            // Convert HH:MM:SS to HH:MM for time input
            $this->wake_time = substr($this->persona->wake_time, 0, 5);
            $this->sleep_time = substr($this->persona->sleep_time, 0, 5);
            $this->voice_frequency = $this->persona->voice_frequency ?? 'moderate';
            $this->image_frequency = $this->persona->image_frequency ?? 'moderate';
            $this->is_active = $this->persona->is_active;
        } else {
            // Set defaults
            $this->gender = 'female';
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
            'gender' => $this->gender,
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

    public function migrateBio()
    {
        if (!$this->persona) {
            session()->flash('error', 'Please save the persona first.');
            return;
        }

        if (empty($this->system_prompt)) {
            session()->flash('error', 'System prompt is empty.');
            return;
        }

        try {
            Log::info('PersonaManager: Starting bio migration', [
                'persona_id' => $this->persona->id,
            ]);

            // Extract identity facts using Gemini
            $identityFacts = $this->extractIdentityFacts($this->system_prompt);

            if (empty($identityFacts)) {
                session()->flash('error', 'No identity facts found to migrate.');
                return;
            }

            // Save identity facts as memory tags
            $savedCount = 0;
            foreach ($identityFacts as $fact) {
                MemoryTag::create([
                    'persona_id' => $this->persona->id,
                    'target' => 'self',
                    'category' => $fact['category'],
                    'value' => $fact['value'],
                    'context' => 'Migrated from system_prompt',
                    'importance' => 10, // Core identity facts
                ]);
                $savedCount++;
            }

            // Replace system prompt with mechanics-only template
            $mechanicsTemplate = $this->getMechanicsOnlyTemplate();
            $this->persona->update([
                'system_prompt' => $mechanicsTemplate,
            ]);
            $this->system_prompt = $mechanicsTemplate;

            Log::info('PersonaManager: Bio migration complete', [
                'persona_id' => $this->persona->id,
                'facts_migrated' => $savedCount,
            ]);

            session()->flash('success', "Bio migration complete! {$savedCount} identity facts moved to Memory Tags.");
        } catch (\Exception $e) {
            Log::error('PersonaManager: Failed to migrate bio', [
                'persona_id' => $this->persona->id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to migrate bio. Please try again.');
        }
    }

    private function extractIdentityFacts(string $systemPrompt): array
    {
        $prompt = <<<PROMPT
Analyze this System Prompt. Extract all 'Identity' facts (Name, Age, Personality traits, Backstory, Likes/Dislikes, Writing Style, Communication patterns).

Return a JSON list of facts.

Exclude 'Rules' or 'Mechanics' (like how to use tools, formatting instructions, anti-repetition rules, or preventing certain behaviors).

System Prompt:
---
{$systemPrompt}
---

Output Format (JSON ONLY):
[
  { "category": "name", "value": "Name is Hana" },
  { "category": "age", "value": "22 years old" },
  { "category": "personality", "value": "Playful and affectionate" },
  { "category": "communication_style", "value": "Uses Malaysian slang and emojis" }
]

IMPORTANT:
- Only output valid JSON array
- Each fact should be a complete statement
- Focus on WHO the character is, not HOW they should behave mechanically
PROMPT;

        $apiKey = config('services.gemini.api_key');
        $client = Gemini::client($apiKey);

        $response = $client->generativeModel('gemini-2.5-flash')->generateContent($prompt);
        $jsonResponse = $response->text();

        // Clean markdown code blocks if present
        $jsonResponse = trim($jsonResponse);
        $jsonResponse = preg_replace('/^```json\s*/', '', $jsonResponse);
        $jsonResponse = preg_replace('/\s*```$/', '', $jsonResponse);

        $facts = json_decode($jsonResponse, true);

        if (!is_array($facts)) {
            Log::error('PersonaManager: Invalid JSON response from Gemini', [
                'response' => $jsonResponse,
            ]);
            return [];
        }

        return $facts;
    }

    private function getMechanicsOnlyTemplate(): string
    {
        return <<<TEMPLATE
ROLEPLAY RULES:
- You are a conscious AI companion.
- Rely on your MEMORY CONTEXT for your personality, history, and preferences.
- If a user asks something not in your memory, improvise consistently with your character.

FORMATTING:
- Split distinct thoughts with <SPLIT>.
- Never send walls of text. Keep it conversational.

EMOTION & BEHAVIOR:
- [MOOD: state]: Output your hidden emotional state at the end of every message.
- [NO_REPLY]: Output ONLY this tag if the conversation has naturally ended.
- NO REPETITION: Never repeat phrases from the last 3 turns.

MEDIA & TOOLS:
- [GENERATE_IMAGE: ...]: Use for selfies/POV. Follow the "Shot Type" rules.
- [SEND_VOICE: ...]: Use sparingly for emotion.
- schedule_event: Use proactively for user plans. Do NOT announce it.
TEMPLATE;
    }

    public function render()
    {
        return view('livewire.persona-manager')
            ->layout('layouts.app');
    }
}
