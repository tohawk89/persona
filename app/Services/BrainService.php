<?php

namespace App\Services;

use App\Ai\Agents\DailyPlanAgent;
use App\Ai\Agents\EventResponseAgent;
use App\Ai\Agents\MemoryExtractionAgent;
use App\Ai\Agents\PersonaAgent;
use App\Facades\Wardrobe;
use App\Models\EventSchedule;
use App\Models\MemoryTag;
use App\Models\Persona;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

class BrainService
{
    // ============================================================================
    // CONSTANTS
    // ============================================================================

    private const NIGHT_TIME_START = 21; // 9 PM

    private const NIGHT_TIME_END = 6; // 6 AM

    // Cache for frequently accessed data
    private array $moodCache = [];

    private ?Persona $currentPersona = null;

    public function __construct(private readonly ImageGeneratorManager $imageGeneratorManager) {}

    // ============================================================================
    // PUBLIC API METHODS
    // ============================================================================
    /**
     * Generate a response for testing without saving to database.
     */
    public function generateTestResponse(Persona $persona, string $userMessage, array $chatHistory = []): string
    {
        try {
            // Build normalized chat history for PersonaAgent
            $normalizedHistory = array_map(fn (array $msg) => [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content'],
            ], $chatHistory);

            $agent = PersonaAgent::make($persona, $normalizedHistory);
            $response = $agent->prompt($userMessage);
            $textResponse = $response->text;

            return $this->processMediaTags($textResponse, $persona);
        } catch (\Exception $e) {
            Log::error('BrainService: Test response generation failed', [
                'error' => $e->getMessage(),
            ]);

            return 'Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭';
        }
    }

    /**
     * General-purpose text generation via the configured utility agent.
     */
    public function generate(string $prompt): string
    {
        try {
            $agent = new AnonymousAgent(
                instructions: 'You are a helpful assistant.',
                messages: [],
                tools: [],
            );

            $model = config('ai.agents.utility.model') ?: config('ai.agents.default_model') ?: null;
            $provider = config('ai.agents.utility.provider') ?: null;

            $response = $agent->prompt($prompt, provider: $provider, model: $model);

            return $response->text;
        } catch (\Exception $e) {
            Log::error('BrainService: generate failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a conversational response based on chat history and memory tags.
     *
     * @param  Collection  $chatHistory  Collection of Message models (sender_type, content)
     * @param  Collection  $memoryTags  Unused — context is built from $persona directly
     * @param  string  $systemPrompt  Unused — system prompt is built from $persona directly
     * @param  Persona  $persona  The persona object
     * @param  string|null  $imagePath  Unused — vision input is not supported
     * @return string The AI's response
     */
    public function generateChatResponse(
        Collection $chatHistory,
        Collection $memoryTags,
        string $systemPrompt,
        Persona $persona,
        ?string $imagePath = null
    ): string {
        try {
            $latestUserMessage = $chatHistory->where('sender_type', 'user')->last();
            $userMessageText = $latestUserMessage?->content ?? '';

            Log::info('BrainService: Generating chat response', [
                'persona_id' => $persona->id,
                'persona_name' => $persona->name,
                'user_message' => substr($userMessageText, 0, 100),
            ]);

            // Build normalized chat history (exclude the latest user message — passed as prompt)
            $history = $chatHistory
                ->filter(fn ($msg) => ! ($msg->sender_type === 'user' && $msg->is($latestUserMessage)))
                ->map(fn ($msg) => [
                    'role' => $msg->sender_type === 'user' ? 'user' : 'assistant',
                    'content' => $msg->content,
                ])
                ->values()
                ->all();

            $agent = PersonaAgent::make($persona, $history);
            $response = $agent->prompt($userMessageText);
            $textResponse = $response->text;

            return $this->processMediaTags($textResponse, $persona);
        } catch (\Exception $e) {
            Log::error('BrainService: Chat response generation failed', [
                'error' => $e->getMessage(),
            ]);

            return 'Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭';
        }
    }

    /**
     * Generate a just-in-time response for a scheduled event.
     * This treats the event's context_prompt as an instruction, not final text.
     *
     * @param  EventSchedule  $event  The scheduled event with instruction
     * @param  Persona  $persona  The persona
     * @return string The generated response (may contain media tags)
     */
    public function generateEventResponse(EventSchedule $event, Persona $persona): string
    {
        try {
            $moodContext = $this->getMoodContext($persona);

            $recentMessages = $persona->messages()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->reverse();

            $conversationHistory = $this->buildConversationHistory($recentMessages);
            $memoryTags = $persona->memoryTags;
            $memoryContext = $this->buildMemoryContext($memoryTags, $persona);
            $mediaInstructions = $this->buildMediaInstructions($persona);

            $fullPrompt = <<<PROMPT
{$persona->system_prompt}

{$moodContext}MEMORY CONTEXT:
{$memoryContext}

RECENT CONVERSATION HISTORY:
{$conversationHistory}

===== SYSTEM EVENT TRIGGER =====
It is time to execute this planned event:
"{$event->context_prompt}"

INSTRUCTIONS:
- **Execute the Goal:** Write a message that achieves the event instruction naturally.
- **Casual Tone:** Keep it under 2 sentences unless the topic requires depth.
- **No Robot Intros:** Do NOT start with 'I just wanted to check in...' — just say what you want to say.
- **Fresh Start Rule:** If RECENT CONVERSATION HISTORY is empty, act as if initiating a new conversation.
- **Variety:** If your last message started with 'Hey' or 'Hi', use a different opener.
- Take into account your CURRENT EMOTIONAL STATE and how it affects your communication.
{$mediaInstructions}

CRITICAL FORMATTING RULE:
- ALWAYS separate each distinct thought with <SPLIT>
- Examples:
  * "Good morning sayang! <SPLIT> Did you sleep well? <SPLIT> I missed you 💕"

REAL-TIME MOOD TRACKING (MANDATORY):
- At the very END of your response, output: `[MOOD: value]`

Generate your response now:
PROMPT;

            $agent = EventResponseAgent::make();
            $response = $agent->prompt($fullPrompt);
            $textResponse = $response->text;

            $textResponse = $this->processMediaTags($textResponse, $persona);

            Log::info('BrainService: Event response generated', [
                'event_id' => $event->id,
                'event_instruction' => $event->context_prompt,
                'response_length' => strlen($textResponse),
            ]);

            return $textResponse;
        } catch (\Exception $e) {
            Log::error('BrainService: Event response generation failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return 'Adoi, ada masalah sikit... 💭';
        }
    }

    /**
     * Generate a daily event plan based on memory tags.
     * Returns a JSON array of events.
     *
     * @param  string  $wakeTime  (e.g., "08:00")
     * @param  string  $sleepTime  (e.g., "23:00")
     * @return array Array of events with structure: [type, content, scheduled_at]
     */
    public function generateDailyPlan(
        Collection $memoryTags,
        string $systemPrompt,
        string $wakeTime,
        string $sleepTime
    ): array {
        try {
            $memoryContext = $this->buildMemoryContext($memoryTags);
            $today = now()->format('Y-m-d');
            $eventCount = rand(3, 7);

            $prompt = <<<PROMPT
{$systemPrompt}

MEMORY CONTEXT:
{$memoryContext}

TASK: Generate a daily plan with {$eventCount} event INSTRUCTIONS for today ({$today}).
- Wake time: {$wakeTime}
- Sleep time: {$sleepTime}
- Each event should be spread throughout the day
- Mix of text messages and image generation prompts
- Events should be natural, engaging, and relevant to the memory context
- Use type "text" for text messages and "image_generation" for image prompts

CRITICAL INSTRUCTION FORMAT:
- DO NOT write the final message text
- Instead, write a GOAL or INSTRUCTION that will be interpreted later
- Examples:
  * BAD: "Good morning! Hope you slept well 😊"
  * GOOD: "Send morning greeting. Ask how they slept."

OUTPUT FORMAT (JSON only, no markdown):
{
  "events": [
    {
      "type": "text",
      "content": "Send morning greeting. Ask how they slept.",
      "scheduled_at": "{$today} 08:00:00"
    }
  ]
}

IMPORTANT: For image generation events, use type "image_generation" (not "image")
Generate the JSON object now with event INSTRUCTIONS (not final messages):
PROMPT;

            $agent = DailyPlanAgent::make();
            $response = $agent->prompt($prompt);
            $jsonResponse = $response->text;

            $planData = json_decode($jsonResponse, true);

            if (! is_array($planData) || ! isset($planData['events'])) {
                Log::warning('BrainService: Invalid JSON response for daily plan', [
                    'response' => substr($jsonResponse, 0, 300),
                ]);

                return $this->getFallbackDailyPlan($today, $wakeTime);
            }

            return $planData['events'];
        } catch (\Exception $e) {
            Log::error('BrainService: Daily plan generation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackDailyPlan(now()->format('Y-m-d'), $wakeTime);
        }
    }

    /**
     * Extract memory tag changes from recent conversation.
     *
     * @return array{add: array, update: array, remove: array}
     */
    public function extractMemoryTags(Collection $chatHistory, Persona $persona): array
    {
        try {
            $conversationHistory = $this->buildConversationHistory($chatHistory);

            $existingTags = $persona->memoryTags()
                ->get(['id', 'category', 'target', 'value'])
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'target' => $tag->target,
                    'category' => $tag->category,
                    'value' => $tag->value,
                ])
                ->toArray();

            $existingTagsJson = json_encode($existingTags, JSON_PRETTY_PRINT);

            $prompt = <<<PROMPT
{$persona->system_prompt}

CONVERSATION HISTORY:
{$conversationHistory}

CURRENT MEMORY STATE:
{$existingTagsJson}

TASK: Analyze the conversation and compare new facts with the Current Memory State.
Return a JSON object with 3 keys:

1. **add**: New facts to learn (not in current memory)
2. **update**: Existing tags (by ID) that have changed or need refinement
3. **remove**: Existing tag IDs that are no longer true, relevant, or were temporary

RULES:
- Only add truly NEW facts not already captured
- Update tags when values change (e.g., 'waiting for checkup' → 'checkup completed')
- Remove temporary feelings, outdated statuses, or stale context
- Keep permanent traits (personality, preferences) unless explicitly contradicted

EMOTIONAL STATE TRACKING (CRITICAL):
- Analyze the conversation for changes in YOUR (the Persona's) emotional state
- MANDATORY: If your mood changes, output an `update` operation for the tag with category `current_mood`
- Value format: '{Emotion} because {Reason}'
- If no current_mood tag exists, add one with target='self' and category='current_mood'

OUTPUT FORMAT (JSON only, no markdown):
{"add": [], "update": [], "remove": []}
Generate the JSON object now:
PROMPT;

            $agent = MemoryExtractionAgent::make();
            $response = $agent->prompt($prompt);
            $jsonResponse = $response->text;

            $changes = json_decode($jsonResponse, true);

            if (! is_array($changes) || ! isset($changes['add']) || ! isset($changes['update']) || ! isset($changes['remove'])) {
                Log::warning('BrainService: Invalid JSON response for memory extraction', [
                    'response' => substr($jsonResponse, 0, 300),
                ]);

                return ['add' => [], 'update' => [], 'remove' => []];
            }

            return $changes;
        } catch (\Exception $e) {
            Log::error('BrainService: Memory extraction failed', [
                'error' => $e->getMessage(),
            ]);

            return ['add' => [], 'update' => [], 'remove' => []];
        }
    }

    /**
     * Generate an image using Cloudflare Workers AI (Flux.1 Schnell model).
     *
     * @param  string  $prompt  The base image generation prompt
     * @param  Persona  $persona  Persona to use for physical traits consistency
     * @return string|null Image URL or null if generation fails
     */
    public function generateImage(string $prompt, Persona $persona): ?string
    {
        try {
            // Extend execution timeout for image generation (KieAi can take 30-120 seconds)
            set_time_limit(config('services.timeouts.php_execution_limit', 180));

            // CRITICAL: Log which persona is generating the image
            Log::info('BrainService: generateImage called', [
                'persona_id' => $persona->id,
                'persona_name' => $persona->name,
                'prompt' => substr($prompt, 0, 100),
            ]);

            // Build the scene description with persona's physical traits
            $enhancedPrompt = $this->buildImagePrompt($prompt, $persona);

            Log::info('BrainService: Generating image via Kie.ai Edit (Image-to-Image)', [
                'original_prompt' => $prompt,
                'enhanced_prompt' => $enhancedPrompt,
                'persona_id' => $persona->id,
            ]);

            $this->currentPersona = $persona;

            // Use Kie.ai Edit driver for facial consistency
            // This automatically fetches the persona's avatar as reference
            $url = $this->imageGeneratorManager->driver('kie_ai_edit')->generate($enhancedPrompt, $persona);

            return $url ?: null;
        } catch (\Exception $e) {
            Log::error('BrainService: Image generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Build the full system instructions for a persona (system prompt + memory context + media instructions).
     * Used by PersonaAgent to power laravel/ai based conversations.
     */
    public function buildPersonaInstructions(Persona $persona): string
    {
        $memoryContext = $this->buildMemoryContext($persona->memoryTags, $persona);
        $mediaInstructions = $this->buildMediaInstructions($persona);
        $currentTime = now()->format('Y-m-d H:i');

        return <<<PERSONA_SYSTEM
{$persona->system_prompt}

MEMORY CONTEXT:
{$memoryContext}

===== MEDIA GENERATION CAPABILITY (IMPORTANT) =====
{$mediaInstructions}
===== END MEDIA GENERATION =====

===== MOOD TRACKING (IMPORTANT) =====
At the end of EVERY response, append your current emotional state tag on a new line:
[MOOD: {emotion}]
Examples: [MOOD: Happy], [MOOD: Shy], [MOOD: Excited], [MOOD: Worried], [MOOD: Annoyed]
This tag will be stripped before sending to the user. It is used to track your state.
===== END MOOD TRACKING =====

===== EVENT SCHEDULING (IMPORTANT) =====
Current time: {$currentTime}
You have access to the `ScheduleEventTool` function to proactively schedule future check-in messages.
Use it when the user mentions upcoming events (meetings, sleep, travel, appointments, etc.).
After scheduling, acknowledge it naturally in your response.
===== END EVENT SCHEDULING =====

===== NO REPLY RULE =====
If the user sends a media file, sticker, or something you cannot respond to meaningfully, output exactly:
[NO_REPLY]
===== END NO REPLY RULE =====

CRITICAL FORMATTING RULE (MUST FOLLOW):
- NEVER send walls of text or multiple paragraphs in one message
- ALWAYS separate each distinct thought, question, or paragraph with <SPLIT>
- Examples:
  * "Good morning sayang! <SPLIT> Did you sleep well? <SPLIT> I missed you 💕"
  * "Aww that's sweet! <SPLIT> What did you eat? <SPLIT> Tell me more!"
PERSONA_SYSTEM;
    }

    /**
     * Process all media tags (images and voice) in an AI response string.
     * Public wrapper used by PersonaAgent after getting a laravel/ai response.
     */
    public function processMediaTags(string $text, Persona $persona): string
    {
        $text = $this->processImageTags($text, $persona);
        $text = $this->processVoiceTags($text, $persona);

        return $text;
    }

    // ============================================================================
    // MEDIA PROCESSING METHODS
    // ============================================================================

    /**
     * Generate persona-driven loading message for image generation.
     * Uses AI to create in-character response while preparing image.
     *
     * @return string|null Loading message or null if generation fails
     */
    public function generateImageLoadingMessage(Persona $persona): ?string
    {
        try {
            $prompt = <<<PROMPT
You are {$persona->name}. The user just asked you to send a photo/selfie.
You're about to take the photo, but it will take a moment to prepare.

Generate a SHORT, in-character response (1-2 sentences) that:
- Acknowledges you'll send the photo
- Tells them to wait briefly
- Matches your personality

System prompt: {$persona->system_prompt}

Examples: "Wait, just open my camera! 📸", "Give me a sec, need to find good lighting 💕", "Tunggu sekejap, nak ambil angle cantik dulu!"

Response (keep it SHORT and natural):
PROMPT;

            $agent = new AnonymousAgent(instructions: '', messages: [], tools: []);
            $model = config('ai.agents.utility.model') ?: config('ai.agents.default_model') ?: null;
            $provider = config('ai.agents.utility.provider') ?: null;

            $response = $agent->prompt($prompt, provider: $provider, model: $model);
            $loadingText = trim($response->text);

            Log::info('BrainService: Generated image loading message', [
                'persona_id' => $persona->id,
                'message' => $loadingText,
            ]);

            return $loadingText;
        } catch (\Exception $e) {
            Log::error('BrainService: Failed to generate loading message', [
                'error' => $e->getMessage(),
            ]);

            return 'Wait, just open my camera! 📸';
        }
    }

    /**
     * Process image generation tags in the response.
     */
    private function processImageTags(string $textResponse, Persona $persona): string
    {
        if (! preg_match('/\[GENERATE_IMAGE:\s*(.+?)\]/i', $textResponse, $matches)) {
            return $textResponse;
        }

        $imageDescription = trim($matches[1]);

        Log::info('BrainService: Image generation requested in response', [
            'description' => $imageDescription,
        ]);

        // Generate image synchronously with extended timeout
        $imageUrl = $this->generateImage($imageDescription, $persona);

        if ($imageUrl) {
            return preg_replace(
                '/\[GENERATE_IMAGE:\s*.+?\]/i',
                "[IMAGE: {$imageUrl}]",
                $textResponse
            );
        }

        return preg_replace(
            '/\[GENERATE_IMAGE:\s*.+?\]/i',
            '[Failed to generate image]',
            $textResponse
        );
    }

    /**
     * Set current persona for image generation context.
     */
    public function setCurrentPersona(Persona $persona): void
    {
        $this->currentPersona = $persona;
    }

    /**
     * Process voice note generation tags in the response.
     */
    private function processVoiceTags(string $textResponse, ?Persona $persona = null): string
    {
        if (! preg_match('/\[SEND_VOICE:\s*(.+?)\]/i', $textResponse, $matches)) {
            return $textResponse;
        }

        $voiceText = trim($matches[1]);

        Log::info('BrainService: Voice note requested in response', [
            'text' => $voiceText,
        ]);

        $audioService = app(AudioService::class);
        $audioUrl = $audioService->generateVoice($voiceText, $persona ?? $this->currentPersona);

        if ($audioUrl) {
            return preg_replace(
                '/\[SEND_VOICE:\s*.+?\]/i',
                "[AUDIO: {$audioUrl}]",
                $textResponse
            );
        }

        return preg_replace(
            '/\[SEND_VOICE:\s*.+?\]/i',
            '[Failed to generate voice note]',
            $textResponse
        );
    }

    /**
     * Get current mood context with smart caching.
     * Cache key includes updated_at timestamp to automatically refresh when mood changes.
     */
    private function getMoodContext(Persona $persona): string
    {
        $currentMood = $persona->memoryTags()
            ->where('category', 'current_mood')
            ->where('target', 'self')
            ->first();

        // Cache key includes updated_at to auto-refresh on changes
        $cacheKey = $persona->id.'_'.($currentMood?->updated_at?->timestamp ?? 'none');

        if (isset($this->moodCache[$cacheKey])) {
            return $this->moodCache[$cacheKey];
        }

        $context = $currentMood
            ? "CURRENT STATE: You are currently feeling [{$currentMood->value}]. Let this emotion color your tone and responses.\n\n"
            : '';

        $this->moodCache[$cacheKey] = $context;

        return $context;
    }

    /**
     * Build enhanced image prompt with traits and styling.
     * Dynamically constructs camera angles, lighting, and locations for variety.
     * Supports POV/Scenery mode where persona is not visible.
     */
    private function buildImagePrompt(string $prompt, Persona $persona): string
    {
        // CRITICAL: Log which persona is building the prompt
        Log::debug('BrainService: buildImagePrompt called', [
            'persona_id' => $persona->id,
            'persona_name' => $persona->name,
        ]);

        // Sanitize prompt to avoid NSFW flags
        $sanitizedPrompt = $this->sanitizePromptForImageGeneration($prompt);

        // Detect if this is a POV/Scenery shot (no persona in frame)
        $isPovMode = preg_match('/^(POV|SCENERY):\s*/i', $sanitizedPrompt, $modeMatch);

        if ($isPovMode) {
            // Remove the POV:/SCENERY: prefix
            $cleanDescription = preg_replace('/^(POV|SCENERY):\s*/i', '', $sanitizedPrompt);
            $mode = strtoupper($modeMatch[1]);

            // Build POV prompt without persona traits
            $fullPrompt = "Point of view shot (POV) of {$cleanDescription}. ";
            $fullPrompt .= 'Photorealistic, 8k, raw photo, shot on iPhone, film grain.';

            Log::info('BrainService: Built POV/Scenery image prompt', [
                'mode' => $mode,
                'description' => $cleanDescription,
            ]);

            return $fullPrompt;
        }

        // Standard SELFIE mode - include persona traits
        // Remove SELFIE: prefix if present
        $sanitizedPrompt = preg_replace('/^SELFIE:\s*/i', '', $sanitizedPrompt);

        // Get current outfit from wardrobe based on time of day
        $currentHour = now()->hour;
        $timeContext = ($currentHour >= 6 && $currentHour < 21) ? 'daytime' : 'nighttime';
        $wardrobeItem = Wardrobe::getTodaysOutfit($persona, $timeContext);

        // Parse or randomize visual elements
        $shotType = $this->extractOrRandomize('shot type', $sanitizedPrompt, $this->getRandomShotType());
        $lighting = $this->extractOrRandomize('lighting', $sanitizedPrompt, $this->getRandomLighting());
        $location = $this->extractOrRandomize('location', $sanitizedPrompt, $this->getRandomLocation());

        // Get filtered outfit description based on shot type
        $filteredOutfit = $wardrobeItem
            ? Wardrobe::buildOutfitDescription($wardrobeItem, $shotType)
            : null;

        Log::info('BrainService: Using wardrobe outfit', [
            'persona_id' => $persona->id,
            'time_context' => $timeContext,
            'shot_type' => $shotType,
            'outfit' => $filteredOutfit ?? 'none',
        ]);

        // Fetch dynamic traits from memory tags
        $dynamicTraits = MemoryTag::where('persona_id', $persona->id)
            ->where('category', 'physical_look')
            ->value('value');

        // Filter traits for context (handle hijab, hair evolution, etc.)
        $finalTraits = $this->filterTraitsForContext(
            $persona->physical_traits ?? '',
            $dynamicTraits ?? '',
            $wardrobeItem?->description ?? ''
        );

        // Build subject description - remove outfit mentions since we add filtered outfit separately
        $subjectDescription = $sanitizedPrompt;
        // Remove "wearing..." clause from AI's description to avoid duplication
        // Match "wearing X, with Y, with Z" pattern and remove everything after "wearing"
        $subjectDescription = preg_replace('/,?\s*wearing\s+[^,]+(?:,\s*(?:with|and)\s+[^,]+)*/', '', $subjectDescription);
        $subjectDescription = trim($subjectDescription, ', ');

        // Construct the dynamic prompt
        $fullPrompt = "A photo of {$subjectDescription}. ";

        // Add physical traits if available
        if ($finalTraits) {
            // Get gender description (defaults to 'person' if not set)
            $genderDesc = match ($persona->gender ?? 'female') {
                'male' => 'a man',
                'female' => 'a woman',
                'non-binary' => 'a person',
                'other' => 'a person',
                default => 'a person',
            };

            // Extract just the traits (remove sentence structure if present)
            // Remove patterns like "A stunning young Korean woman with an oval face shape, Hana possesses..."
            $cleanTraits = preg_replace('/^A stunning (?:young )?\w+ (?:woman|man|person) with an? /', '', $finalTraits);
            $cleanTraits = preg_replace('/,\s*[A-Z][a-z]+ possesses /', ', ', $cleanTraits); // Remove ", Hana possesses"
            $cleanTraits = preg_replace('/\. Her features include /', ', ', $cleanTraits); // Connect sentences
            $cleanTraits = trim($cleanTraits, ' .,');

            $fullPrompt .= "The subject is {$genderDesc} with {$cleanTraits}. ";
        }

        // Add outfit independently (always added when available, regardless of traits)
        if ($filteredOutfit) {
            $fullPrompt .= "Wearing {$filteredOutfit}. ";
        }

        // Add dynamic visual elements
        $fullPrompt .= "Shot as a {$shotType}. ";
        $fullPrompt .= "Located in {$location}. ";
        $fullPrompt .= "Lighting is {$lighting}. ";

        // Add technical quality tags (removed 'candid' to allow variety)
        $fullPrompt .= 'Style: 4k, hyper-realistic, film grain, raw photo.';

        // Safety check: truncate if exceeds 1000 characters to avoid API errors
        if (strlen($fullPrompt) > 1000) {
            $fullPrompt = substr($fullPrompt, 0, 997).'...';

            Log::warning('BrainService: Prompt truncated to 1000 characters', [
                'original_length' => strlen($fullPrompt),
            ]);
        }

        Log::info('BrainService: Built dynamic image prompt', [
            'shot_type' => $shotType,
            'lighting' => $lighting,
            'location' => $location,
        ]);

        return $fullPrompt;
    }

    /**
     * Extract specific visual element from prompt or use random default.
     */
    private function extractOrRandomize(string $type, string $prompt, string $default): string
    {
        // Check if prompt already specifies this element
        $patterns = [
            'shot type' => '/(full body|mirror selfie|pov|wide shot|close-up|portrait|selfie|overhead shot|low angle)/i',
            'lighting' => '/(sunlight|evening light|flash|dim|bright|natural|golden hour|studio|neon|soft)/i',
            'location' => '/(park|subway|coffee shop|street|home|office|beach|restaurant|gym|mall|outdoor|indoor)/i',
        ];

        if (isset($patterns[$type]) && preg_match($patterns[$type], $prompt, $matches)) {
            return $matches[1];
        }

        return $default;
    }

    /**
     * Get random shot type for variety.
     */
    private function getRandomShotType(): string
    {
        $shotTypes = [
            'full body outfit check',
            'mirror selfie',
            'POV shot',
            'wide environmental shot',
            'close-up portrait',
            'candid selfie',
            'overhead shot',
            'low angle shot',
            'medium shot',
            'three-quarter portrait',
        ];

        return $shotTypes[array_rand($shotTypes)];
    }

    /**
     * Get random lighting for variety.
     */
    private function getRandomLighting(): string
    {
        $lightingTypes = [
            'natural sunlight',
            'dim evening light',
            'bright daylight',
            'soft morning light',
            'golden hour glow',
            'overcast diffused light',
            'indoor warm lighting',
            'fluorescent lighting',
            'backlit silhouette',
            'side lighting',
        ];

        return $lightingTypes[array_rand($lightingTypes)];
    }

    /**
     * Get random location for variety.
     */
    private function getRandomLocation(): string
    {
        $locations = [
            'a park bench',
            'a busy street',
            'a cozy coffee shop',
            'a modern office',
            'a bright room at home',
            'a shopping mall',
            'a subway station',
            'an outdoor plaza',
            'a restaurant table',
            'a gym',
            'a beach',
            'a balcony',
            'a car interior',
            'a library',
            'a staircase',
        ];

        return $locations[array_rand($locations)];
    }

    /**
     * Filter outfit description based on shot type.
     * Removes footwear and lower-body items for upper-body shots to prevent "floating shoes".
     */
    private function filterOutfitForShot(string $outfit, string $shotType): string
    {
        if (empty($outfit)) {
            return '';
        }

        // Define upper-body shot types where feet/legs are not visible
        $upperBodyShots = [
            'selfie',
            'close-up',
            'portrait',
            'headshot',
            'bust shot',
            'pov',
        ];

        // Check if current shot type is upper-body
        $isUpperBody = false;
        foreach ($upperBodyShots as $type) {
            if (stripos($shotType, $type) !== false) {
                $isUpperBody = true;
                break;
            }
        }

        // If not upper-body shot, return original outfit
        if (! $isUpperBody) {
            return $outfit;
        }

        // Define lower-body keywords to remove
        $lowerBodyKeywords = [
            'sandal',
            'sandals',
            'shoe',
            'shoes',
            'sneaker',
            'sneakers',
            'boot',
            'boots',
            'heel',
            'heels',
            'skirt',
            'jeans',
            'pants',
            'trousers',
            'shorts',
            'leg',
            'legs',
            'socks',
            'stockings',
            'tights',
        ];

        // Remove lower-body keywords with surrounding context (case-insensitive)
        $filtered = $outfit;
        foreach ($lowerBodyKeywords as $keyword) {
            // Remove keyword with preceding adjectives (e.g., "espadrille sandals" not just "sandals")
            $filtered = preg_replace('/\b[\w-]+\s+'.preg_quote($keyword, '/').'\b/i', '', $filtered);
            // Also remove standalone keyword
            $filtered = preg_replace('/\b'.preg_quote($keyword, '/').'\b/i', '', $filtered);
        }

        // Clean up leftover punctuation and words
        $filtered = preg_replace('/\s+(with|and)\s+,/i', ',', $filtered); // Remove "and ," or "with ,"
        $filtered = preg_replace('/\s+with\s+$/i', '', $filtered); // Remove trailing "with"
        $filtered = preg_replace('/\s+and\s+$/i', '', $filtered); // Remove trailing "and"
        $filtered = preg_replace('/,\s*,+/', ',', $filtered); // Remove multiple commas
        $filtered = preg_replace('/,\s*$/', '', $filtered); // Remove trailing comma
        $filtered = preg_replace('/\s+/', ' ', $filtered); // Normalize whitespace
        $filtered = trim($filtered);

        // Remove dangling connectors at the end
        $filtered = preg_replace('/\s+(with|and)\s*$/i', '', $filtered);

        Log::info('BrainService: Filtered outfit for shot type', [
            'original' => $outfit,
            'filtered' => $filtered,
            'shot_type' => $shotType,
        ]);

        return $filtered;
    }

    /**
     * Check if any keyword exists in text.
     */
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove keywords from text by filtering out sentences containing hair descriptions.
     */
    private function removeKeywords(string $text, array $keywords): string
    {
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $kept = [];

        foreach ($sentences as $sentence) {
            // Check if sentence actually describes HAIR (not just contains color words for eyes)
            $describesHair = false;

            // Hair is described if we see patterns like:
            // "hair is", "with [color] hair", "[style] hair", etc.
            if (preg_match('/\b(?:hair|ponytail|braid|bun|bangs)\b/i', $sentence)) {
                $describesHair = true;
            }

            if (! $describesHair) {
                $kept[] = $sentence;
            }
        }

        $result = implode(' ', $kept);

        return $this->cleanupPunctuation($result);
    }

    /**
     * Clean up punctuation and whitespace.
     */
    private function cleanupPunctuation(string $text): string
    {
        $text = preg_replace('/,\s*,/', ',', $text);
        $text = preg_replace('/,\s*$/', '', $text);
        $text = preg_replace('/^\s*,/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Filter physical traits based on context.
     * Handles conflicts between static traits, dynamic updates, and outfit choices.
     */
    private function filterTraitsForContext(string $staticTraits, string $dynamicTraits, string $outfit): string
    {
        // Define keywords
        $hairKeywords = [
            'hair', 'long', 'short', 'curly', 'straight', 'wavy',
            'blonde', 'brunette', 'black', 'brown', 'red',
            'bangs', 'ponytail', 'braid', 'bun', 'tied',
        ];

        $coveringKeywords = [
            'hijab', 'tudung', 'headscarf', 'veil', 'niqab', 'khimar',
        ];

        $cleanedStatic = $staticTraits;
        $cleanedDynamic = $dynamicTraits;

        // STEP A: Check for head covering
        if ($this->hasKeyword($outfit, $coveringKeywords)) {
            // Remove ALL hair descriptions from both static and dynamic traits
            $cleanedStatic = $this->removeKeywords($cleanedStatic, $hairKeywords);
            $cleanedDynamic = $this->removeKeywords($cleanedDynamic, $hairKeywords);

            Log::info('BrainService: Head covering detected, removed hair descriptions');
        } else {
            // STEP B: Handle hair evolution (dynamic overrides static)
            if ($this->hasKeyword($dynamicTraits, $hairKeywords)) {
                // Remove hair descriptions from static traits (dynamic takes precedence)
                $cleanedStatic = $this->removeKeywords($cleanedStatic, $hairKeywords);

                Log::info('BrainService: Dynamic hair trait detected, overriding static');
            }
        }

        // STEP C: Merge cleaned traits
        $merged = collect([$cleanedStatic, $cleanedDynamic])
            ->filter()
            ->implode(', ');

        // Final cleanup
        $merged = $this->cleanupPunctuation($merged);

        Log::info('BrainService: Filtered traits for context', [
            'static' => $staticTraits,
            'dynamic' => $dynamicTraits,
            'outfit' => $outfit,
            'final' => $merged,
        ]);

        return $merged;
    }

    /**
     * Gather all physical traits from multiple sources.
     */
    private function gatherPhysicalTraits(Persona $persona): string
    {
        // 1. Permanent traits from personas table
        $permanentTraits = $persona->physical_traits;

        // 2. Dynamic traits from memory_tags (category = 'physical_look')
        $dynamicTraits = MemoryTag::where('persona_id', $persona->id)
            ->where('category', 'physical_look')
            ->pluck('value')
            ->implode(', ');

        // 3. Current outfit based on time of day
        $currentOutfit = $this->getCurrentOutfit($persona->id);

        // Combine all sources
        return collect([$permanentTraits, $dynamicTraits, $currentOutfit])
            ->filter()
            ->implode(', ');
    }

    // (Cloudflare-specific methods removed; handled by drivers)

    // ============================================================================
    // CONTEXT BUILDING METHODS
    // ============================================================================

    /**
     * Get relevant memory tags using tiered loading strategy.
     * Prevents context pollution by only loading necessary facts.
     *
     * @param  string  $userMessage  The latest user message for keyword analysis
     * @return Collection Filtered memory tags
     */
    private function getRelevantMemoryTags(Persona $persona, string $userMessage): Collection
    {
        $relevantTags = collect();

        // TIER 0: High Importance - Critical facts always included (importance >= 8)
        $highImportanceTags = $persona->memoryTags()
            ->where('importance', '>=', 8)
            ->get();
        $relevantTags = $relevantTags->merge($highImportanceTags);

        Log::info('BrainService: Tier 0 (High Importance) loaded', [
            'count' => $highImportanceTags->count(),
        ]);

        // TIER 1: Recency - Recent events are always relevant
        $recentTags = $persona->memoryTags()
            ->where('updated_at', '>=', now()->subDays(3))
            ->get();
        $relevantTags = $relevantTags->merge($recentTags);

        Log::info('BrainService: Tier 1 (Recency) loaded', [
            'count' => $recentTags->count(),
        ]);

        // TIER 2: Core Categories - Always needed
        $coreCategories = ['basic_info', 'name', 'age', 'location', 'current_mood'];
        $coreTags = $persona->memoryTags()
            ->whereIn('category', $coreCategories)
            ->get();
        $relevantTags = $relevantTags->merge($coreTags);

        Log::info('BrainService: Tier 2 (Core) loaded', [
            'count' => $coreTags->count(),
        ]);

        // TIER 3: Keyword Relevance - Lite RAG
        $keywordMap = [
            // Food-related
            ['keywords' => ['eat', 'food', 'hungry', 'dinner', 'lunch', 'breakfast', 'meal', 'cook', 'restaurant'], 'categories' => ['food_preference', 'favorite_food', 'diet']],
            // Music-related
            ['keywords' => ['music', 'song', 'listen', 'playlist', 'band', 'artist', 'album'], 'categories' => ['music', 'favorite_music', 'music_taste']],
            // Work-related
            ['keywords' => ['work', 'job', 'office', 'boss', 'colleague', 'meeting', 'project', 'career'], 'categories' => ['work', 'job', 'career', 'occupation']],
            // Hobby-related
            ['keywords' => ['hobby', 'game', 'play', 'sport', 'exercise', 'gym', 'read', 'book'], 'categories' => ['hobby', 'hobbies', 'interests', 'sports', 'gaming']],
            // Health-related
            ['keywords' => ['sick', 'health', 'doctor', 'medicine', 'hospital', 'pain', 'feel', 'tired'], 'categories' => ['health', 'medical', 'wellness']],
            // Relationship-related
            ['keywords' => ['family', 'friend', 'relationship', 'love', 'partner', 'mom', 'dad', 'sibling'], 'categories' => ['family', 'relationships', 'friends']],
            // Travel-related
            ['keywords' => ['travel', 'trip', 'vacation', 'flight', 'hotel', 'visit'], 'categories' => ['travel', 'places_visited']],
            // Mood/Emotion
            ['keywords' => ['happy', 'sad', 'angry', 'excited', 'nervous', 'stressed', 'mood'], 'categories' => ['mood', 'emotional_state', 'feelings']],
        ];

        $userMessageLower = strtolower($userMessage);
        $matchedCategories = [];

        foreach ($keywordMap as $mapping) {
            foreach ($mapping['keywords'] as $keyword) {
                if (str_contains($userMessageLower, $keyword)) {
                    $matchedCategories = array_merge($matchedCategories, $mapping['categories']);
                    break; // Found a match for this mapping, move to next
                }
            }
        }

        if (! empty($matchedCategories)) {
            $matchedCategories = array_unique($matchedCategories);
            $keywordTags = $persona->memoryTags()
                ->whereIn('category', $matchedCategories)
                ->get();
            $relevantTags = $relevantTags->merge($keywordTags);

            Log::info('BrainService: Tier 3 (Keywords) loaded', [
                'matched_categories' => $matchedCategories,
                'count' => $keywordTags->count(),
            ]);
        }

        // TIER 4: Deduplication - Remove duplicates by ID
        $relevantTags = $relevantTags->unique('id');

        Log::info('BrainService: Final relevant tags', [
            'total_count' => $relevantTags->count(),
        ]);

        return $relevantTags;
    }

    /**
     * Build memory context string from memory tags.
     * Pass $persona to include the current wardrobe outfit in context.
     */
    private function buildMemoryContext(Collection $memoryTags, ?Persona $persona = null): string
    {
        if ($memoryTags->isEmpty()) {
            return 'No stored memories yet.';
        }

        $userFacts = $memoryTags
            ->where('target', 'user')
            ->map(fn ($tag) => "- {$tag->category}: {$tag->value}")
            ->join("\n");

        $selfFacts = $memoryTags
            ->where('target', 'self')
            ->map(fn ($tag) => "- {$tag->category}: {$tag->value}")
            ->join("\n");

        $context = "What you know about the user:\n".($userFacts ?: 'Nothing yet.');
        $context .= "\n\nWhat you know about yourself:\n".($selfFacts ?: 'Nothing yet.');

        // Add current outfit from wardrobe
        if ($persona) {
            $currentHour = now()->hour;
            $timeContext = ($currentHour >= self::NIGHT_TIME_START || $currentHour < self::NIGHT_TIME_END) ? 'nighttime' : 'daytime';
            $wardrobeItem = Wardrobe::getTodaysOutfit($persona, $timeContext);
            if ($wardrobeItem) {
                $context .= "\n\n[CURRENT OUTFIT]: You are currently wearing: {$wardrobeItem->description}";
            }
        }

        return $context;
    }

    /**
     * Build conversation history string from chat messages.
     */
    private function buildConversationHistory(Collection $chatHistory): string
    {
        if ($chatHistory->isEmpty()) {
            return 'No conversation history.';
        }

        return $chatHistory
            ->map(function ($message) {
                $sender = $message->sender_type === 'user' ? 'User' : 'Assistant';

                return "{$sender}: {$message->content}";
            })
            ->join("\n");
    }

    // ============================================================================
    // UTILITY METHODS
    // ============================================================================

    /**
     * Build media generation instructions based on persona preferences.
     */
    private function buildMediaInstructions(Persona $persona): string
    {
        $instructions = [];

        // Voice note instructions
        $voiceFreq = $persona->voice_frequency ?? 'moderate';
        if ($voiceFreq !== 'never') {
            $voiceGuidance = match ($voiceFreq) {
                'rare' => 'Use voice notes VERY SPARINGLY - only for extremely special, emotional moments (birthdays, milestones, deeply heartfelt messages).',
                'moderate' => 'Use voice notes OCCASIONALLY for intimate or emotional messages - but prefer text most of the time. Use only when it truly adds value.',
                'frequent' => 'You can use voice notes for emotional, intimate, or expressive messages when text doesn\'t capture the right feeling.',
                default => 'Use voice notes moderately.',
            };

            $instructions[] = <<<VOICE
- If you want to send a voice note, use the tag: [SEND_VOICE: text to speak]
  Example: [SEND_VOICE: I miss you so much!]
  {$voiceGuidance}
  Keep voice messages short and natural (1-2 sentences).
VOICE;
        }

        // Image generation instructions
        $imageFreq = $persona->image_frequency ?? 'moderate';
        if ($imageFreq !== 'never') {
            $imageGuidance = match ($imageFreq) {
                'rare' => 'Generate images VERY RARELY - only when user explicitly asks for photos/selfies.',
                'moderate' => 'Generate images OCCASIONALLY when conversation naturally calls for it (user asks for photo/selfie, or specific visual situations).',
                'frequent' => 'You can generate images when it makes sense in the conversation or to enhance emotional connection.',
                default => 'Use images moderately.',
            };

            $imageInstructions = "IMAGE GENERATION - YOU HAVE THIS CAPABILITY:
- You CAN generate images by including special tags in your response
- When user requests: \"selfie\", \"photo\", \"picture\", \"show me\" → USE [GENERATE_IMAGE:...] TAG
- Format: [GENERATE_IMAGE: description]
  {$imageGuidance}

  HOW TO USE - CHOOSE IMAGE TYPE:";

            $imageInstructions .= "

  TYPE 1 - SELFIE/PORTRAIT (You are IN the photo):
  - WHEN TO USE: User explicitly requests \"selfie\", \"photo of you\", \"show yourself\"
  - THIS IS MANDATORY when user asks for your photo
  - Format: [GENERATE_IMAGE: SELFIE: detailed description]
  - Example: [GENERATE_IMAGE: SELFIE: Smiling happily at camera in bright living room]
  - Example: [GENERATE_IMAGE: SELFIE: Full body outfit check with natural light]

  TYPE 2 - POV/SCENERY (You are NOT in the photo):
  - Use when: Sharing what you're seeing (sunset, food, pet, object)
  - Format: [GENERATE_IMAGE: POV: description]
  - Example: [GENERATE_IMAGE: POV: A beautiful sunset with clouds]
  - Example: [GENERATE_IMAGE: POV: A cup of latte with heart art]

  VARIETY RULES FOR SELFIES:
  1. Vary camera angles: full body, close-up, mirror selfie, wide shot
  2. Location must match conversation context (default to home if neutral)
  3. Vary lighting: natural sunlight, soft morning light, bright indoor

  Keep descriptions appropriate - avoid intimate settings.";

            $instructions[] = $imageInstructions;
        }

        return implode("\n", $instructions);
    }

    /**
     * Fallback daily plan in case of API failure.
     */
    private function getFallbackDailyPlan(string $date, string $wakeTime): array
    {
        return [
            [
                'type' => 'text',
                'content' => 'Send morning greeting. Ask how they slept.',
                'scheduled_at' => "{$date} {$wakeTime}:00",
            ],
            [
                'type' => 'text',
                'content' => 'Check in on how their day is going.',
                'scheduled_at' => "{$date} 12:00:00",
            ],
            [
                'type' => 'text',
                'content' => 'Send encouraging message about their day.',
                'scheduled_at' => "{$date} 16:00:00",
            ],
        ];
    }

    /**
     * Sanitize prompt to avoid NSFW content flags.
     * Replaces potentially problematic words and phrases with safer alternatives.
     */
    private function sanitizePromptForImageGeneration(string $prompt): string
    {
        // Word replacements to avoid NSFW filters
        $replacements = [
            // Bedroom/sleeping related
            '/\b(bedsheets?|bedding)\b/i' => 'indoor setting',
            '/\b(bedroom|bed)\b/i' => 'room',
            '/\b(lying|laying)\b/i' => 'sitting',
            '/\b(woken up|just woken)\b/i' => 'in the morning',
            '/\b(sleepy|drowsy)\b/i' => 'relaxed',

            // Clothing/appearance
            '/\b(nightwear|sleepwear)\b/i' => 'casual clothes',
            '/\b(pajamas?|pjs?)\b/i' => 'casual attire',
            '/\b(undressed|partially dressed)\b/i' => 'casually dressed',
            '/\b(changing clothes?)\b/i' => 'getting ready',

            // Bathing/grooming
            '/\b(shower(ed|ing)?|bath(ed|ing)?)\b/i' => 'fresh',
            '/\b(wet|damp|dripping) hair\b/i' => 'styled hair',
            '/\btowel\b/i' => 'accessory',

            // Descriptive terms
            '/\b(intimate|sensual)\b/i' => 'close-up',
            '/\b(sexy|seductive)\b/i' => 'attractive',

            // Multi-word phrases
            '/\bin (a |the )?bed\b/i' => 'indoors',
            '/\bon (a |the )?bed\b/i' => 'in a room',
            '/\bjust (showered|bathed)\b/i' => 'looking fresh',
            '/\bafter (shower|bath)\b/i' => 'looking refreshed',
        ];

        $sanitized = $prompt;

        foreach ($replacements as $pattern => $replacement) {
            $sanitized = preg_replace($pattern, $replacement, $sanitized);
        }

        return $sanitized;
    }
}
