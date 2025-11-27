<?php

namespace App\Services;

use Gemini;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Persona;
use App\Models\MemoryTag;
use App\Models\EventSchedule;

class GeminiBrainService
{
    // ============================================================================
    // CONSTANTS
    // ============================================================================

    private const GEMINI_MODEL = 'gemini-2.5-flash';
    private const CLOUDFLARE_MODEL = '@cf/black-forest-labs/flux-1-schnell';
    private const MAX_RETRIES = 3;
    private const INITIAL_RETRY_DELAY = 1;
    private const IMAGE_NUM_STEPS = 4;
    private const NIGHT_TIME_START = 21; // 9 PM
    private const NIGHT_TIME_END = 6; // 6 AM

    // ============================================================================
    // PUBLIC API METHODS
    // ============================================================================
    /**
     * Generate a response for testing without saving to database.
     *
     * @param Persona $persona
     * @param string $userMessage
     * @param array $chatHistory
     * @return string
     */
    public function generateTestResponse(Persona $persona, string $userMessage, array $chatHistory = []): string
    {
        try {
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            // Build memory context
            $memoryTags = $persona->memoryTags;
            $memoryContext = $this->buildMemoryContext($memoryTags);

            // Build conversation history
            $conversationText = '';
            foreach ($chatHistory as $msg) {
                $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
                $conversationText .= "{$role}: {$msg['content']}\n";
            }
            $conversationText .= "User: {$userMessage}\n";

            // Get media usage instructions based on persona preferences
            $mediaInstructions = $this->buildMediaInstructions($persona);

            // Construct full prompt with image generation capability
            $fullPrompt = <<<PROMPT
{$persona->system_prompt}

MEMORY CONTEXT:
{$memoryContext}

CONVERSATION HISTORY:
{$conversationText}

INSTRUCTIONS:
- Respond naturally as the persona, taking into account the memory context and conversation history.
{$mediaInstructions}

CRITICAL FORMATTING RULE (MUST FOLLOW):
- NEVER send walls of text or multiple paragraphs in one message
- ALWAYS separate each distinct thought, question, or paragraph with <SPLIT>
- Examples:
  * "Good morning sayang! <SPLIT> Did you sleep well? <SPLIT> I missed you 💕"
  * "Aww that's sweet! <SPLIT> What did you eat? <SPLIT> Tell me more!"
Assistant:
PROMPT;

            // Generate response with retry logic
            $textResponse = $this->callGeminiWithRetry($client, $fullPrompt);

            // Process media tags (images and voice notes)
            $textResponse = $this->processImageTags($textResponse, $persona);
            $textResponse = $this->processVoiceTags($textResponse);

            return $textResponse;
        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Test response generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return user-friendly message instead of technical error
            return "Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭";
        }
    }

    /**
     * Simple Gemini API call for general-purpose text generation.
     *
     * @param string $prompt The prompt to send to Gemini
     * @return string The AI's response
     */
    public function callGemini(string $prompt): string
    {
        try {
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            $result = $client->generativeModel(self::GEMINI_MODEL)->generateContent($prompt);
            return $result->text();
        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Simple Gemini call failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a conversational response based on chat history and memory tags.
     * Supports multimodal input (text + image) via Gemini Vision API.
     *
     * @param Collection $chatHistory Collection of messages (sender_type, content)
     * @param Collection $memoryTags Collection of memory tags (target, key, value)
     * @param string $systemPrompt The persona's system prompt
     * @param Persona $persona The persona object (for image generation)
     * @param string|null $imagePath Optional path to image file for vision analysis
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
            // Get latest user message for keyword analysis
            $latestUserMessage = $chatHistory
                ->where('sender_type', 'user')
                ->last();
            $userMessageText = $latestUserMessage?->content ?? '';

            // Use tiered memory loading instead of all tags
            $relevantMemoryTags = $this->getRelevantMemoryTags($persona, $userMessageText);

            // Build the context prompt with relevant memories only
            $memoryContext = $this->buildMemoryContext($relevantMemoryTags);
            $conversationHistory = $this->buildConversationHistory($chatHistory);

            // Get media usage instructions based on persona preferences
            $mediaInstructions = $this->buildMediaInstructions($persona);

            // Get current mood for context injection
            $currentMood = $relevantMemoryTags
                ->where('category', 'current_mood')
                ->where('target', 'self')
                ->first();
            $moodContext = $currentMood
                ? "CURRENT STATE: You are currently feeling [{$currentMood->value}]. Let this emotion color your tone and responses.\n\n"
                : "";

            // Construct the full prompt with media generation instructions and function calling
            $fullPrompt = <<<PROMPT
{$systemPrompt}

{$moodContext}MEMORY CONTEXT:
{$memoryContext}

CONVERSATION HISTORY:
{$conversationHistory}

INSTRUCTIONS:
- Respond naturally as the persona, taking into account the memory context and conversation history.
{$mediaInstructions}

CRITICAL FORMATTING RULE (MUST FOLLOW):
- NEVER send walls of text or multiple paragraphs in one message
- ALWAYS separate each distinct thought, question, or paragraph with <SPLIT>
- Examples:
  * "Good morning sayang! <SPLIT> Did you sleep well? <SPLIT> I missed you 💕"
  * "Aww that's sweet! <SPLIT> What did you eat? <SPLIT> Tell me more!"
- Even if you have 2 sentences about different topics, use <SPLIT> between them

PROACTIVE SCHEDULING TOOL:
- You have access to a `schedule_event` tool. Use it proactively!
- If the user mentions a future event (waking up, leaving, meeting, appointment), schedule a message for that time to check on them or wish them luck.
- Do not ask for permission—just do it silently and naturally.
- Examples: "I have work at 2 PM" → schedule check-in at 2 PM, "Wake me at 7 AM" → schedule wake-up call at 7 AM

CRITICAL RULE: When you use the `schedule_event` tool, DO NOT announce it to the user.
- Bad response: 'I have set a reminder for 10 PM.'
- Good response: 'Okay! Hope you feel better by 10 PM then! Good luck!'
- Act as if you will just remember it naturally. Your reply must focus on the User's sentiment (e.g., them feeling tired), NOT on the fact that you scheduled a task.

REAL-TIME MOOD TRACKING (MANDATORY):
- At the very END of your response, output your current emotional state using this format: `[MOOD: value]`
- Examples: 'That is so funny! 😂 [MOOD: Cheerful]' or 'I don't want to talk right now. [MOOD: Annoyed]'
- This tag will be hidden from the user, so be honest about your feelings
- Always include this tag with every response

CRITICAL RULE: NO REPETITION
- Before replying, review the CONVERSATION HISTORY carefully
- NEVER repeat exact phrases, sentences, or specific sentiments from your last 3 messages
- If you already said "I am worried", "That sounds great", or any other phrase recently, DO NOT say it again
- Vary your vocabulary, expressions, and reactions to keep the conversation fresh and natural
- Keep the conversation moving forward—don't get stuck in a loop of politeness or recycled responses
- Examples of what NOT to do:
  * User: "I have a meeting" → You: "Good luck with the meeting!"
  * User: "I have another meeting" → You: "Good luck with the meeting!" ❌ (REPETITIVE)
- Instead, vary your response: "Hope it goes smoothly!", "Knock 'em dead!", "You've got this! 💪"
PROMPT;

            // Call Gemini API with retry logic and function calling support
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            $textResponse = $this->callGeminiWithFunctionCalling($client, $fullPrompt, $persona, $imagePath);

            // Process media tags (images and voice notes)
            $textResponse = $this->processImageTags($textResponse, $persona);
            $textResponse = $this->processVoiceTags($textResponse);

            return $textResponse;
        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Chat response generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'had_image' => $imagePath !== null,
            ]);

            return "Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭";
        }
    }

    /**
     * Generate a daily event plan based on memory tags.
     * Returns a JSON array of events.
     *
     * @param Collection $memoryTags
     * @param string $systemPrompt
     * @param string $wakeTime (e.g., "08:00")
     * @param string $sleepTime (e.g., "23:00")
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
            $eventCount = rand(3, 7); // Random number of events between 3 and 7

            $prompt = <<<PROMPT
{$systemPrompt}

MEMORY CONTEXT:
{$memoryContext}

TASK: Generate a daily plan with {$eventCount} events for today ({$today}).
- Wake time: {$wakeTime}
- Sleep time: {$sleepTime}
- Each event should be spread throughout the day
- Mix of text messages and image generation prompts
- Events should be natural, engaging, and relevant to the memory context
- Use type "text" for text messages and "image_generation" for image prompts

IMPORTANT: Also decide on your outfit for the day:
- Choose a daily outfit (e.g., "white floral sundress", "office wear - black blazer and slacks", "casual jeans and pink hoodie")
- Choose nightwear/sleepwear (e.g., "silk pajamas", "oversized t-shirt", "satin nightgown")

OUTPUT FORMAT (JSON only, no markdown):
{
  "daily_outfit": "white floral sundress with sandals",
  "night_outfit": "silk pajamas",
  "events": [
    {
      "type": "text",
      "content": "Good morning! Hope you slept well 😊",
      "scheduled_at": "{$today} 08:00:00"
    },
    {
      "type": "image_generation",
      "content": "A cozy coffee shop scene with morning sunlight",
      "scheduled_at": "{$today} 10:30:00"
    }
  ]
}

IMPORTANT: For image generation events, use type "image_generation" (not "image")

Generate the JSON object now:
PROMPT;

            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);
            $result = $client->generativeModel(self::GEMINI_MODEL)
                ->withGenerationConfig(
                    new GenerationConfig(
                        temperature: 0.7,
                        responseMimeType: ResponseMimeType::APPLICATION_JSON
                    )
                )
                ->generateContent($prompt);

            $jsonResponse = $result->text();

            // Parse and validate JSON
            $planData = json_decode($jsonResponse, true);

            if (!is_array($planData) || !isset($planData['events'])) {
                Log::warning('GeminiBrainService: Invalid JSON response from Gemini for daily plan');
                return [
                    'events' => $this->getFallbackDailyPlan($today, $wakeTime),
                    'daily_outfit' => null,
                    'night_outfit' => null,
                ];
            }

            return [
                'events' => $planData['events'],
                'daily_outfit' => $planData['daily_outfit'] ?? null,
                'night_outfit' => $planData['night_outfit'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Daily plan generation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'events' => $this->getFallbackDailyPlan(now()->format('Y-m-d'), $wakeTime),
                'daily_outfit' => null,
                'night_outfit' => null,
            ];
        }
    }

    /**
     * Extract memory tags from recent conversation.
     * Returns an array of memory tags.
     *
     * @param Collection $chatHistory
     * @param string $systemPrompt
     * @return array Array of memory tags with structure: [target, key, value]
     */
    public function extractMemoryTags(
        Collection $chatHistory,
        Persona $persona
    ): array {
        try {
            $conversationHistory = $this->buildConversationHistory($chatHistory);

            // Fetch existing memory tags
            $existingTags = $persona->memoryTags()->get(['id', 'category', 'target', 'value'])->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'target' => $tag->target,
                    'category' => $tag->category,
                    'value' => $tag->value,
                ];
            })->toArray();

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
- Did the User make you happy, angry, sad, shy, annoyed, excited, or any other emotion?
- MANDATORY: If your mood changes, output an `update` operation for the tag with category `current_mood`
- Value format: '{Emotion} because {Reason}' (e.g., 'Happy because User complimented me', 'Annoyed because User ignored my question')
- If no current_mood tag exists, add one with target='self' and category='current_mood'

OUTPUT FORMAT (JSON only, no markdown):
{
  "add": [
    {
      "target": "user",
      "category": "favorite_drink",
      "value": "coffee with oat milk",
      "context": "Mentioned during morning chat"
    }
  ],
  "update": [
    {
      "id": 12,
      "value": "checkup completed",
      "context": "Updated after user confirmed"
    }
  ],
  "remove": [14, 15]
}

If there are no changes, return: {"add": [], "update": [], "remove": []}
Generate the JSON object now:
PROMPT;

            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);
            $result = $client->generativeModel(self::GEMINI_MODEL)
                ->withGenerationConfig(
                    new GenerationConfig(
                        temperature: 0.3,
                        responseMimeType: ResponseMimeType::APPLICATION_JSON
                    )
                )
                ->generateContent($prompt);

            $jsonResponse = $result->text();
            $changes = json_decode($jsonResponse, true);

            if (!is_array($changes) || !isset($changes['add']) || !isset($changes['update']) || !isset($changes['remove'])) {
                Log::warning('GeminiBrainService: Invalid JSON response from Gemini for memory extraction', [
                    'response' => $jsonResponse,
                ]);
                return ['add' => [], 'update' => [], 'remove' => []];
            }

            return $changes;
        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Memory extraction failed', [
                'error' => $e->getMessage(),
            ]);

            return ['add' => [], 'update' => [], 'remove' => []];
        }
    }

    /**
     * Generate an image using Cloudflare Workers AI (Flux.1 Schnell model).
     *
     * @param string $prompt The base image generation prompt
     * @param Persona $persona Persona to use for physical traits consistency
     * @return string|null Image URL or null if generation fails
     */
    public function generateImage(string $prompt, Persona $persona): ?string
    {
        try {
            $accountId = config('services.cloudflare.account_id');
            $apiToken = config('services.cloudflare.api_token');

            if (!$accountId || !$apiToken) {
                Log::error('GeminiBrainService: Cloudflare credentials not configured');
                return null;
            }

            // Build enhanced prompt with safety, traits, and styling
            $enhancedPrompt = $this->buildImagePrompt($prompt, $persona);

            Log::info('GeminiBrainService: Generating image with Cloudflare AI', [
                'original_prompt' => $prompt,
                'enhanced_prompt' => $enhancedPrompt,
            ]);

            // Call Cloudflare Workers AI API
            $response = $this->callCloudflareImageAPI($accountId, $apiToken, $enhancedPrompt);

            if (!$response->successful()) {
                $errorBody = $response->body();

                Log::error('GeminiBrainService: Cloudflare API request failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                ]);

                // Check if it's an NSFW content error
                if ($response->status() === 400 && str_contains($errorBody, 'NSFW')) {
                    Log::warning('GeminiBrainService: Prompt flagged as NSFW, attempting with safer prompt');

                    // Retry with an extremely safe, minimal prompt
                    $safePrompt = "Close-up portrait photograph, artistic framing showing partial face. Professional photography, soft lighting, high quality.";
                    $response = $this->callCloudflareImageAPI($accountId, $apiToken, $safePrompt);

                    if (!$response->successful()) {
                        Log::error('GeminiBrainService: Retry with safe prompt also failed');
                        return null;
                    }
                } else {
                    return null;
                }
            }

            // Process and save the generated image
            return $this->saveGeneratedImage($response);

        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Image generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    // ============================================================================
    // MEDIA PROCESSING METHODS
    // ============================================================================

    /**
     * Process image generation tags in the response.
     */
    private function processImageTags(string $textResponse, Persona $persona): string
    {
        if (!preg_match('/\[GENERATE_IMAGE:\s*(.+?)\]/i', $textResponse, $matches)) {
            return $textResponse;
        }

        $imageDescription = trim($matches[1]);

        Log::info('GeminiBrainService: Image generation requested in response', [
            'description' => $imageDescription,
        ]);

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
     * Process voice note generation tags in the response.
     */
    private function processVoiceTags(string $textResponse): string
    {
        if (!preg_match('/\[SEND_VOICE:\s*(.+?)\]/i', $textResponse, $matches)) {
            return $textResponse;
        }

        $voiceText = trim($matches[1]);

        Log::info('GeminiBrainService: Voice note requested in response', [
            'text' => $voiceText,
        ]);

        $audioService = app(AudioService::class);
        $audioUrl = $audioService->generateVoice($voiceText);

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
     * Build enhanced image prompt with traits and styling.
     */
    private function buildImagePrompt(string $prompt, Persona $persona): string
    {
        // Define the "Realism Booster" - forces photorealistic, candid style
        $styleBooster = "shot on iPhone, candid photography, natural lighting, grainy texture, skin pores, slight imperfections, 4k, hyper-realistic";

        // Sanitize prompt to avoid NSFW flags
        $sanitizedPrompt = $this->sanitizePromptForImageGeneration($prompt);

        // Get current outfit based on time of day
        $currentOutfit = $this->getCurrentOutfit($persona->id);

        // Construct the final prompt following the Realism Formula
        // Order: Subject & Action → The Person → The Outfit → The Vibe
        $fullPrompt = "A candid photo of {$sanitizedPrompt}. ";

        if ($persona->physical_traits) {
            $fullPrompt .= "The subject is a woman with {$persona->physical_traits}";

            if ($currentOutfit) {
                $fullPrompt .= ", wearing {$currentOutfit}";
            }

            $fullPrompt .= ". ";
        }

        $fullPrompt .= "Style: {$styleBooster}.";

        // Safety check: truncate if exceeds 1000 characters to avoid API errors
        if (strlen($fullPrompt) > 1000) {
            $fullPrompt = substr($fullPrompt, 0, 997) . '...';

            Log::warning('GeminiBrainService: Prompt truncated to 1000 characters', [
                'original_length' => strlen($fullPrompt),
            ]);
        }

        return $fullPrompt;
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

    /**
     * Get current outfit based on time of day.
     */
    private function getCurrentOutfit(int $personaId): ?string
    {
        $currentHour = now()->hour;
        $isNightTime = $currentHour >= self::NIGHT_TIME_START || $currentHour < self::NIGHT_TIME_END;

        $category = $isNightTime ? 'night_outfit' : 'daily_outfit';

        return MemoryTag::where('persona_id', $personaId)
            ->where('category', $category)
            ->value('value');
    }

    // ============================================================================
    // CLOUDFLARE API METHODS
    // ============================================================================

    /**
     * Call Cloudflare Workers AI image generation API.
     */
    private function callCloudflareImageAPI(string $accountId, string $apiToken, string $prompt)
    {
        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/" . self::CLOUDFLARE_MODEL;

        return Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$apiToken}"])
            ->post($endpoint, [
                'prompt' => $prompt,
                'num_steps' => self::IMAGE_NUM_STEPS,
            ]);
    }

    /**
     * Save generated image from Cloudflare response.
     */
    private function saveGeneratedImage($response): ?string
    {
        $data = $response->json();

        if (!isset($data['result']['image'])) {
            Log::error('GeminiBrainService: No image in Cloudflare response', [
                'response' => $data,
            ]);
            return null;
        }

        // Decode Base64 image
        $base64Image = $data['result']['image'];
        $decodedImage = base64_decode($base64Image);

        if ($decodedImage === false) {
            Log::error('GeminiBrainService: Failed to decode Base64 image');
            return null;
        }

        // Generate unique filename and save
        $filename = Str::uuid() . '.jpg';
        $path = "generated_images/{$filename}";

        Storage::disk('public')->put($path, $decodedImage);

        $url = Storage::disk('public')->url($path);

        Log::info('GeminiBrainService: Image generated successfully', [
            'filename' => $filename,
            'url' => $url,
        ]);

        return $url;
    }

    // ============================================================================
    // GEMINI API METHODS
    // ============================================================================

    /**
     * Call Gemini API with retry logic for overload handling.
     * Supports multimodal input (text + image) when imagePath is provided.
     *
     * @param mixed $client Gemini client instance
     * @param string $prompt Text prompt for generation
     * @param string|null $imagePath Optional path to image file for vision analysis
     * @return string Generated text response
     */
    private function callGeminiWithRetry($client, string $prompt, ?string $imagePath = null): string
    {
        $retryDelay = self::INITIAL_RETRY_DELAY;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $model = $client->generativeModel(self::GEMINI_MODEL);

                // If image provided, use HTTP API directly (SDK has issues with multimodal)
                if ($imagePath && file_exists($imagePath)) {
                    // Encode image as base64
                    $imageData = base64_encode(file_get_contents($imagePath));
                    $mimeType = mime_content_type($imagePath);

                    Log::info('GeminiBrainService: Calling Gemini with multimodal input', [
                        'mime_type' => $mimeType,
                        'image_size' => strlen($imageData),
                    ]);

                    // Use HTTP API directly for multimodal (SDK has compatibility issues)
                    $apiKey = config('services.gemini.api_key');
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . self::GEMINI_MODEL . ":generateContent?key={$apiKey}";

                    $payload = [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inline_data' => [
                                            'mime_type' => $mimeType,
                                            'data' => $imageData,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $httpResponse = Http::timeout(60)->post($url, $payload);

                    if ($httpResponse->successful()) {
                        $data = $httpResponse->json();
                        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        Log::info('GeminiBrainService: Multimodal response received', [
                            'response_length' => strlen($text),
                        ]);
                        return $text;
                    } else {
                        Log::error('GeminiBrainService: Multimodal API failed', [
                            'status' => $httpResponse->status(),
                            'body' => $httpResponse->body(),
                        ]);
                        throw new \Exception('Gemini multimodal API failed: ' . $httpResponse->body());
                    }
                } else {
                    // Standard text-only generation
                    $response = $model->generateContent($prompt);
                }

                return $response->text();
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();

                $isOverloaded = str_contains($errorMessage, 'overloaded') || str_contains($errorMessage, 'rate limit');

                if ($isOverloaded && $attempt < self::MAX_RETRIES) {
                    Log::warning("GeminiBrainService: Model overloaded, retrying in {$retryDelay}s (attempt {$attempt}/" . self::MAX_RETRIES . ")");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                    continue;
                }

                if ($isOverloaded) {
                    Log::error('GeminiBrainService: Max retries reached, model still overloaded');
                    return "Ada hal sikit... Cuba sekejap lagi ya? 😊";
                }

                // Log vision-specific errors separately
                if ($imagePath) {
                    Log::error('GeminiBrainService: Vision API call failed', [
                        'error' => $errorMessage,
                        'attempt' => $attempt,
                    ]);
                }

                throw $e;
            }
        }

        return "Ada hal sikit... Cuba sekejap lagi ya? 😊";
    }

    /**
     * Call Gemini API with function calling support for proactive event scheduling.
     * Handles function calls and recursively gets final text response.
     */
    private function callGeminiWithFunctionCalling($client, string $prompt, Persona $persona, ?string $imagePath = null): string
    {
        $apiKey = config('services.gemini.api_key');
        $currentTime = now()->format('Y-m-d H:i');

        // Define the schedule_event tool
        $tools = [
            [
                'function_declarations' => [
                    [
                        'name' => 'schedule_event',
                        'description' => "Schedule a future message to the user. Use this PROACTIVELY when the user mentions future plans (meetings, waking up, travel, appointments). Current time is: {$currentTime}",
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'time' => [
                                    'type' => 'STRING',
                                    'description' => "The time to send the message (Format: YYYY-MM-DD HH:MM). Convert relative times (like '2 PM today', 'tomorrow 9 AM') to absolute timestamp based on current time: {$currentTime}",
                                ],
                                'topic' => [
                                    'type' => 'STRING',
                                    'description' => 'The context/topic of the message (e.g., "Wake up check", "Good luck for meeting", "Check on travel")',
                                ],
                            ],
                            'required' => ['time', 'topic'],
                        ],
                    ],
                ],
            ],
        ];

        try {
            // Use HTTP API for function calling (SDK may have limited support)
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . self::GEMINI_MODEL . ":generateContent?key={$apiKey}";

            // Build request payload
            $parts = [['text' => $prompt]];

            // Add image if provided
            if ($imagePath && file_exists($imagePath)) {
                $imageData = base64_encode(file_get_contents($imagePath));
                $mimeType = mime_content_type($imagePath);
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $imageData,
                    ],
                ];
            }

            $payload = [
                'contents' => [
                    [
                        'parts' => $parts,
                    ],
                ],
                'tools' => $tools,
            ];

            Log::info('GeminiBrainService: Calling Gemini with function calling support');

            $httpResponse = Http::timeout(60)->post($url, $payload);

            if (!$httpResponse->successful()) {
                Log::error('GeminiBrainService: Function calling API failed', [
                    'status' => $httpResponse->status(),
                    'body' => $httpResponse->body(),
                ]);
                // Fallback to standard call
                return $this->callGeminiWithRetry($client, $prompt, $imagePath);
            }

            $data = $httpResponse->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                Log::warning('GeminiBrainService: No candidate in function calling response');
                return $this->callGeminiWithRetry($client, $prompt, $imagePath);
            }

            // Check if response contains a function call
            $parts = $candidate['content']['parts'] ?? [];
            $functionCall = null;

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCall = $part['functionCall'];
                    break;
                }
            }

            // CASE A: Function call detected → Execute and recurse
            if ($functionCall && $functionCall['name'] === 'schedule_event') {
                $args = $functionCall['args'] ?? [];
                $time = $args['time'] ?? null;
                $topic = $args['topic'] ?? null;

                Log::info('GeminiBrainService: Function call detected', [
                    'function' => 'schedule_event',
                    'time' => $time,
                    'topic' => $topic,
                ]);

                // Create event in database
                $scheduledAt = \Carbon\Carbon::parse($time);
                $contextPrompt = "User has an event now: {$topic}. Send a natural, caring message checking on them or wishing them luck.";

                EventSchedule::create([
                    'persona_id' => $persona->id,
                    'type' => 'text',
                    'context_prompt' => $contextPrompt,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'pending',
                ]);

                Log::info('GeminiBrainService: Event scheduled successfully', [
                    'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                    'topic' => $topic,
                ]);

                // Send function response back to Gemini to get final text reply
                $functionResponsePayload = [
                    'contents' => [
                        [
                            'parts' => $parts, // Original prompt
                        ],
                        [
                            'role' => 'model',
                            'parts' => [
                                [
                                    'functionCall' => $functionCall,
                                ],
                            ],
                        ],
                        [
                            'role' => 'function',
                            'parts' => [
                                [
                                    'functionResponse' => [
                                        'name' => 'schedule_event',
                                        'response' => [
                                            'success' => true,
                                            'message' => "Event scheduled for {$time}: {$topic}",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'tools' => $tools,
                ];

                $finalResponse = Http::timeout(60)->post($url, $functionResponsePayload);

                if ($finalResponse->successful()) {
                    $finalData = $finalResponse->json();
                    $text = $finalData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    Log::info('GeminiBrainService: Final text response received after function call');
                    return $text;
                } else {
                    Log::error('GeminiBrainService: Failed to get final response after function call');
                    return "Okay, I'll remind you! 💕";
                }
            }

            // CASE B: No function call → Return text response
            $text = $parts[0]['text'] ?? '';
            return $text;

        } catch (\Exception $e) {
            Log::error('GeminiBrainService: Function calling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to standard call
            return $this->callGeminiWithRetry($client, $prompt, $imagePath);
        }
    }

    // ============================================================================
    // CONTEXT BUILDING METHODS
    // ============================================================================

    /**
     * Get relevant memory tags using tiered loading strategy.
     * Prevents context pollution by only loading necessary facts.
     *
     * @param Persona $persona
     * @param string $userMessage The latest user message for keyword analysis
     * @return Collection Filtered memory tags
     */
    private function getRelevantMemoryTags(Persona $persona, string $userMessage): Collection
    {
        $relevantTags = collect();

        // TIER 1: Recency - Recent events are always relevant
        $recentTags = $persona->memoryTags()
            ->where('updated_at', '>=', now()->subDays(3))
            ->get();
        $relevantTags = $relevantTags->merge($recentTags);

        Log::info('GeminiBrainService: Tier 1 (Recency) loaded', [
            'count' => $recentTags->count(),
        ]);

        // TIER 2: Core Categories - Always needed
        $coreCategories = ['daily_outfit', 'night_outfit', 'basic_info', 'name', 'age', 'location', 'current_mood'];
        $coreTags = $persona->memoryTags()
            ->whereIn('category', $coreCategories)
            ->get();
        $relevantTags = $relevantTags->merge($coreTags);

        Log::info('GeminiBrainService: Tier 2 (Core) loaded', [
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

        if (!empty($matchedCategories)) {
            $matchedCategories = array_unique($matchedCategories);
            $keywordTags = $persona->memoryTags()
                ->whereIn('category', $matchedCategories)
                ->get();
            $relevantTags = $relevantTags->merge($keywordTags);

            Log::info('GeminiBrainService: Tier 3 (Keywords) loaded', [
                'matched_categories' => $matchedCategories,
                'count' => $keywordTags->count(),
            ]);
        }

        // TIER 4: Deduplication - Remove duplicates by ID
        $relevantTags = $relevantTags->unique('id');

        Log::info('GeminiBrainService: Final relevant tags', [
            'total_count' => $relevantTags->count(),
        ]);

        return $relevantTags;
    }

    /**
     * Build memory context string from memory tags.
     */
    private function buildMemoryContext(Collection $memoryTags): string
    {
        if ($memoryTags->isEmpty()) {
            return "No stored memories yet.";
        }

        // Separate outfit tags from other memories
        $outfitCategories = ['daily_outfit', 'night_outfit'];

        $userFacts = $memoryTags
            ->where('target', 'user')
            ->whereNotIn('category', $outfitCategories)
            ->map(fn($tag) => "- {$tag->category}: {$tag->value}")
            ->join("\n");

        $selfFacts = $memoryTags
            ->where('target', 'self')
            ->whereNotIn('category', $outfitCategories)
            ->map(fn($tag) => "- {$tag->category}: {$tag->value}")
            ->join("\n");

        $context = "What you know about the user:\n" . ($userFacts ?: "Nothing yet.");
        $context .= "\n\nWhat you know about yourself:\n" . ($selfFacts ?: "Nothing yet.");

        // Add current outfit context
        $currentOutfit = $this->getCurrentOutfitFromMemory($memoryTags);
        if ($currentOutfit) {
            $context .= "\n\n[CURRENT OUTFIT]: You are currently wearing: {$currentOutfit}";
        }

        return $context;
    }

    /**
     * Get current outfit from memory tags collection based on time.
     */
    private function getCurrentOutfitFromMemory(Collection $memoryTags): ?string
    {
        $currentHour = now()->hour;
        $isNightTime = $currentHour >= self::NIGHT_TIME_START || $currentHour < self::NIGHT_TIME_END;

        $category = $isNightTime ? 'night_outfit' : 'daily_outfit';

        $outfit = $memoryTags->where('category', $category)->first();

        return $outfit?->value;
    }

    /**
     * Build conversation history string from chat messages.
     */
    private function buildConversationHistory(Collection $chatHistory): string
    {
        if ($chatHistory->isEmpty()) {
            return "No conversation history.";
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
            $voiceGuidance = match($voiceFreq) {
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
            $imageGuidance = match($imageFreq) {
                'rare' => 'Generate images VERY RARELY - only when user explicitly asks for photos/selfies.',
                'moderate' => 'Generate images OCCASIONALLY when conversation naturally calls for it (user asks for photo/selfie, or specific visual situations).',
                'frequent' => 'You can generate images when it makes sense in the conversation or to enhance emotional connection.',
                default => 'Use images moderately.',
            };

            $instructions[] = <<<IMAGE
- If you want to generate an image, use the tag: [GENERATE_IMAGE: description]
  Example: [GENERATE_IMAGE: Portrait of a person smiling at the camera in a bright room]
  {$imageGuidance}
  IMPORTANT: Keep descriptions professional and appropriate. Avoid mentioning beds, bedrooms, or intimate settings.
  Use safe contexts like: coffee shops, parks, streets, studios, bright rooms, outdoor settings.
IMAGE;
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
                'content' => 'Good morning! 🌅',
                'scheduled_at' => "{$date} {$wakeTime}:00",
            ],
            [
                'type' => 'text',
                'content' => 'How are you doing today?',
                'scheduled_at' => "{$date} 12:00:00",
            ],
            [
                'type' => 'text',
                'content' => 'Hope your day is going well! ✨',
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
