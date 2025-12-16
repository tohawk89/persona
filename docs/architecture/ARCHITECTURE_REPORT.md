# AI Virtual Companion - System Architecture Report

**Project:** Laravel 12 AI Persona System  
**AI Stack:** Gemini 2.5 Flash (text + vision), Cloudflare Workers AI (image gen), ElevenLabs (TTS)  
**Communication:** Telegram Bot API with webhook + polling modes  
**Date:** November 26, 2025

---

## 1. Database Schema Overview

### Core Tables

#### `users`
The human user interacting with the AI persona via Telegram.

```php
- id (primary key)
- name (string)
- email (nullable, unique) // Optional if Telegram-only
- telegram_chat_id (nullable, unique) // Key identifier for bot communication
- telegram_username (string) // Added for profile display
- last_interaction_at (timestamp) // Critical for SmartQueue rescheduling logic
- password, remember_token, timestamps
```

**Key Behavior:** `last_interaction_at` updated on every incoming message. SmartQueue checks this to avoid interrupting active conversations (15-minute window).

---

#### `personas`
The AI companion's configuration and personality definition.

```php
- id (primary key)
- user_id (foreign key) // Links persona to specific user
- name (string) // e.g., "Sarah"
- avatar_ref_path (nullable) // Path to uploaded avatar image
- physical_traits (text, nullable) // Base appearance: "Short black hair, brown eyes, petite build"
- system_prompt (text) // Core personality instructions for Gemini
- wake_time (time, default '08:00') // Daily schedule start
- sleep_time (time, default '23:00') // Daily schedule end
- voice_frequency (enum: never/rare/moderate/frequent, default 'moderate')
- image_frequency (enum: never/rare/moderate/frequent, default 'moderate')
- is_active (boolean, default true)
- timestamps
```

**Key Feature:** `physical_traits` combined with memory tag outfits create dynamic image generation prompts. Media frequency controls prevent overuse of voice/images.

---

#### `memory_tags`
Extracted facts about the user and persona from conversations.

```php
- id (primary key)
- persona_id (foreign key, cascade delete)
- target (enum: 'user', 'self') // Is this fact about the user or the AI?
- category (string) // "music", "food", "work", "daily_outfit", "night_outfit"
- value (text) // "likes Linkin Park", "Blue dress with white sneakers"
- context (text, nullable) // Source: "Chat on 24th Nov at 3pm"
- timestamps
```

**Extraction Logic:**
- `ExtractMemoryTags` job runs every 10th message
- Gemini analyzes conversation history and outputs JSON
- Special categories: `daily_outfit` (6 AM–9 PM) and `night_outfit` (9 PM–6 AM) for time-based clothing

**Context Building Example:**
```
USER FACTS:
- music: likes Linkin Park
- work: Software engineer

SELF FACTS:
- daily_outfit: Blue dress with white sneakers
- personality: Sarcastic but caring
```

---

#### `event_schedules`
Planned messages/images sent by the AI throughout the day.

```php
- id (primary key)
- persona_id (foreign key, cascade delete)
- scheduled_at (datetime) // When to trigger
- type (enum: 'text', 'image_generation', 'wake_up', 'sleep')
- context_prompt (text, nullable) // Instructions for Gemini to generate content
- status (enum: 'pending', 'sent', 'rescheduled', 'cancelled', default 'pending')
- timestamps
```

**Smart Queue Logic:**
- `ProcessScheduledEvents` command runs every minute (cron)
- Checks if `last_interaction_at < 15 minutes ago`
- If user is active, reschedules event +30 minutes
- If user is idle, executes event and marks `status = 'sent'`

**Daily Plan Generation:**
- `GenerateDailyPlan` command runs at wake time
- Gemini generates 3–7 events for the day
- Includes outfit assignment for day/night cycles

---

#### `messages`
Complete chat history between user and bot.

```php
- id (primary key)
- user_id (foreign key, cascade delete)
- persona_id (foreign key, cascade delete)
- sender_type (enum: 'user', 'bot')
- content (text, nullable) // Nullable if image-only message
- image_path (string, nullable) // Local path or URL to photo
- is_event_trigger (boolean, default false) // Distinguishes scheduled events from direct replies
- timestamps
- index: (created_at, persona_id) for fast history queries
```

**Critical Persistence Rule:** Bot messages **ALWAYS** saved to DB after sending. This ensures conversation continuity for memory extraction and context building.

---

## 2. The "Brain" - Service Layer

All business logic lives in **singleton services** registered in `AppServiceProvider`. Controllers and jobs are thin wrappers that delegate to services.

### Service Architecture Pattern

```php
// app/Providers/AppServiceProvider.php
$this->app->singleton(GeminiBrainService::class);
$this->app->singleton(TelegramService::class);
$this->app->singleton(SmartQueueService::class);

// Usage via facades
use App\Facades\{GeminiBrain, Telegram, SmartQueue};
```

---

### 2.1 GeminiBrainService

**Purpose:** Core AI interaction with Gemini API (text + vision multimodal).

#### Key Methods

##### `generateChatResponse(Collection $chatHistory, Collection $memoryTags, string $systemPrompt, Persona $persona, ?string $imagePath = null): string`

**Flow:**
1. Build memory context from `$memoryTags` (user facts + self facts)
2. Build conversation history from `$chatHistory` (last 20 messages)
3. Inject media usage instructions based on persona's `voice_frequency` and `image_frequency`:
   ```php
   // Example for 'rare' voice frequency:
   "You can send voice messages, but use them VERY SPARINGLY (1 in 10 messages). 
   Tag format: [SEND_VOICE: text to speak]"
   ```
4. Construct full prompt:
   ```
   {system_prompt}
   
   MEMORY CONTEXT:
   {memory context}
   
   CONVERSATION HISTORY:
   {last 20 messages}
   
   INSTRUCTIONS:
   - Respond naturally as the persona
   {media instructions}
   ```

5. **Text-only:** Call `$client->generativeModel('gemini-2.5-flash')->generateContent($prompt)`
6. **Multimodal (with image):** Use HTTP API directly (SDK has compatibility issues):
   ```php
   POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
   {
     "contents": [{
       "parts": [
         {"text": "{prompt}"},
         {"inline_data": {
           "mime_type": "image/jpeg",
           "data": "{base64_encoded_image}"
         }}
       ]
     }]
   }
   ```

7. Process response tags:
   - `[GENERATE_IMAGE: description]` → Call `generateImage()` → Replace with `[IMAGE: url]`
   - `[SEND_VOICE: text]` → Call AudioService → Replace with `[AUDIO: url]`

8. Return processed text with media URLs

**Retry Logic:** 3 attempts with exponential backoff (1s, 2s, 4s) for rate limit/overload errors.

---

##### `generateImage(string $description, Persona $persona): string`

**Hybrid Physical Traits Implementation:**

```php
private function buildImagePrompt(string $description, Persona $persona): string
{
    // 1. Get base physical traits
    $traits = $persona->physical_traits; // "Short black hair, brown eyes"
    
    // 2. Get current outfit based on time
    $outfit = $this->getCurrentOutfit($persona);
    // Returns daily_outfit (6 AM–9 PM) or night_outfit (9 PM–6 AM)
    
    // 3. Combine with description + realism formula
    return "Candid iPhone photograph, natural lighting, 8K HDR quality. " .
           "Subject: {$traits}, wearing {$outfit}. " .
           "Scene: {$description}. " .
           "Shot on iPhone 14 Pro, shallow depth of field, film grain, realistic skin texture.";
}
```

**API Call:**
- **Service:** Cloudflare Workers AI
- **Model:** `@cf/black-forest-labs/flux-1-schnell`
- **Steps:** 4 (fast generation, ~3-5s)
- **Storage:** Saves to `storage/app/public/generated_images/{uuid}.jpg`
- **Returns:** Public URL `/storage/generated_images/{uuid}.jpg`

**Safety:** All prompts pass through `sanitizePromptForImageGeneration()` with NSFW keyword filtering. Flagged prompts → generic safe fallback.

---

##### `extractMemoryTags(Collection $recentMessages, Persona $persona): array`

**Trigger:** Every 10th message in conversation.

**Process:**
1. Build conversation summary (last 10 messages)
2. Prompt Gemini with JSON schema:
   ```json
   {
     "tags": [
       {"target": "user", "category": "music", "value": "likes Linkin Park", "context": "..."},
       {"target": "self", "category": "personality", "value": "sarcastic", "context": "..."}
     ]
   }
   ```
3. Validate JSON response with `ResponseMimeType::JSON`
4. Return array of MemoryTagDTO objects

**Validation:** Requires `target`, `category`, `value` fields. Malformed responses logged and skipped.

---

### 2.2 AudioService

**Purpose:** Text-to-speech via ElevenLabs API.

```php
public function generateVoiceNote(string $text, string $voiceId): ?string
{
    // 1. Call ElevenLabs TTS API
    $response = Http::withHeaders([
        'xi-api-key' => config('services.elevenlabs.api_key'),
    ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
        'text' => $text,
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.75,
        ],
    ]);
    
    // 2. Save audio to storage/app/public/voice_notes/{uuid}.mp3
    $path = "voice_notes/{$uuid}.mp3";
    Storage::disk('public')->put($path, $response->body());
    
    // 3. Return public URL
    return Storage::disk('public')->url($path);
}
```

**Voice ID:** Configured per persona in `.env` (e.g., `ELEVENLABS_VOICE_ID=21m00Tcm4TlvDq8ikWAM`).

---

### 2.3 TelegramService

**Purpose:** Wrapper around `telegram-bot-sdk` v3.15 for type-safe method calls.

#### Key Methods

##### `sendMessage(string|int $chatId, string $message, array $options = []): bool`
- Sends HTML-formatted text
- Logs success/failure with chat_id and message length

##### `sendPhoto(string|int $chatId, string $photo, ?string $caption = null): bool`
```php
// Critical: Convert URL to local file path for Telegram SDK
if (filter_var($photo, FILTER_VALIDATE_URL)) {
    $relativePath = str_replace('/storage/', '', parse_url($photo, PHP_URL_PATH));
    $localPath = storage_path('app/public/' . $relativePath);
    $photo = InputFile::create($localPath); // SDK requires InputFile wrapper
}
```

##### `sendVoice(string|int $chatId, string $voice, array $options = []): bool`
- Same InputFile logic as `sendPhoto()`
- Sets `duration` metadata if available

##### `sendChatAction(string|int $chatId, string $action = 'typing'): bool`
- Shows typing indicator, `upload_photo`, `record_voice` actions
- **Critical UX:** Sent immediately on webhook receipt to prevent "ghost" silence

##### `getFile(array $params): array`
- Calls Telegram API to get file metadata (`file_path`, `file_size`)
- Used to download user-sent photos

##### `parseUpdate(array $update): array`
- Extracts chat_id, message_id, text, user info from Telegram webhook payload

---

### 2.4 SmartQueueService

**Purpose:** Prevent scheduled events from interrupting active conversations.

```php
public function updateUserInteraction(User $user): void
{
    $user->update(['last_interaction_at' => now()]);
}

public function isUserActive(User $user, int $windowMinutes = 15): bool
{
    if (!$user->last_interaction_at) return false;
    return $user->last_interaction_at->diffInMinutes(now()) < $windowMinutes;
}

public function processEvent(EventSchedule $event, callable $callback): void
{
    $user = $event->persona->user;
    
    if ($this->isUserActive($user)) {
        // Reschedule +30 minutes
        $event->update([
            'scheduled_at' => now()->addMinutes(30),
            'status' => 'rescheduled',
        ]);
        Log::info('SmartQueue: Event rescheduled due to active conversation');
    } else {
        // Execute event
        $callback($event);
        $event->update(['status' => 'sent']);
    }
}
```

**Usage in Command:**
```php
// app/Console/Commands/ProcessScheduledEvents.php
$events = EventSchedule::where('status', 'pending')
    ->where('scheduled_at', '<=', now())
    ->get();

foreach ($events as $event) {
    SmartQueue::processEvent($event, function($event) {
        $response = GeminiBrain::generateChatResponse(...);
        Telegram::sendMessage($chatId, $response);
    });
}
```

---

## 3. Communication Flow (Critical Path)

### 3.1 TelegramWebhookController

**Endpoint:** `POST /api/telegram/webhook`  
**Middleware:** None (public endpoint, security handled internally)

#### Step-by-Step Logic

```php
public function webhook(Request $request): JsonResponse
{
    // STEP 1: SECRET VALIDATION (OUTSIDE try-catch to allow abort)
    $webhookSecret = env('TELEGRAM_WEBHOOK_SECRET');
    if ($webhookSecret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $webhookSecret) {
        Log::warning('Invalid secret token', ['ip' => $request->ip()]);
        abort(403, 'Forbidden: Invalid secret token');
    }

    try {
        // STEP 2: PARSE UPDATE & DETECT PHOTO
        $payload = $request->all();
        $data = Telegram::parseUpdate($payload);
        $message = $payload['message'] ?? null;
        
        $imagePath = null;
        if ($message && isset($message['photo'])) {
            // Get largest photo (last in array)
            $photo = end($message['photo']);
            $fileId = $photo['file_id'];
            
            // Download via Telegram API
            $fileInfo = Telegram::getFile(['file_id' => $fileId]);
            $filePath = $fileInfo['file_path'];
            $botToken = config('services.telegram.bot_token');
            $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            
            $imageData = file_get_contents($fileUrl);
            $uuid = Str::uuid();
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $tempPath = storage_path("app/private/temp/{$uuid}.{$extension}");
            
            // Create temp directory if needed
            if (!is_dir(storage_path('app/private/temp'))) {
                mkdir(storage_path('app/private/temp'), 0755, true);
            }
            
            file_put_contents($tempPath, $imageData);
            $imagePath = $tempPath;
        }
        
        // Get text or caption
        $text = $data['text'] ?? ($message['caption'] ?? null);
        if ($imagePath && empty($text)) {
            $text = '[Sent an image]';
        }
        
        if (empty($text) && !$imagePath) {
            return response()->json(['status' => 'ignored', 'reason' => 'No content']);
        }
        
        // STEP 3: SECURITY GATE (Admin-only)
        $chatId = $data['chat_id'];
        $adminId = env('TELEGRAM_ADMIN_ID');
        
        if ($chatId != $adminId) {
            Log::warning('Unauthorized chat_id blocked', ['chat_id' => $chatId]);
            return response()->json(['status' => 'ignored', 'reason' => 'Unauthorized']);
        }
        
        // STEP 4: USER RESOLUTION
        $user = User::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'name' => $data['user']['first_name'] ?? 'Admin',
                'email' => "telegram_{$chatId}@placeholder.local",
                'password' => bcrypt(str()->random(32)),
            ]
        );
        
        // STEP 5: UX INDICATOR (Prevent "ghost" silence)
        Telegram::sendChatAction($chatId, 'typing');
        
        // STEP 6: SAVE USER MESSAGE
        $userMessage = Message::create([
            'user_id' => $user->id,
            'persona_id' => $user->persona?->id,
            'sender_type' => 'user',
            'content' => $text,
            'image_path' => $imagePath, // Store temp path for job
        ]);
        
        // STEP 7: UPDATE INTERACTION TIMESTAMP (SmartQueue)
        $user->update(['last_interaction_at' => now()]);
        
        // STEP 8: DISPATCH ASYNC JOB
        ProcessChatResponse::dispatch($user, $userMessage, $imagePath);
        
        // STEP 9: MEMORY EXTRACTION (every 10th message)
        $messageCount = Message::where('user_id', $user->id)->count();
        if ($messageCount % 10 === 0) {
            ExtractMemoryTags::dispatch($user);
        }
        
        return response()->json(['status' => 'ok']);
        
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['status' => 'error'], 500);
    }
}
```

**Security Highlights:**
- Secret token validation (abort 403 if invalid/missing)
- Admin-only gate (ignores non-admin chat IDs silently)
- Outside try-catch to allow `abort()` to propagate

**Photo Handling:**
- Downloads to `storage/app/private/temp/{uuid}.jpg` (temporary storage)
- Passes temp path to job via constructor
- Job responsible for cleanup after processing

---

### 3.2 ProcessChatResponse (Job)

**Queue:** `database` driver, 3 retries, exponential backoff [5, 15, 30]s

#### Job Logic Flow

```php
public function __construct(
    public User $user,
    public Message $userMessage,
    public ?string $imagePath = null // Temp file path for multimodal
) {}

public function handle(): void
{
    try {
        // STEP 1: TYPING INDICATOR
        Telegram::sendChatAction($this->user->telegram_chat_id, 'typing');
        
        // STEP 2: GET PERSONA & CONTEXT
        $persona = $this->user->persona;
        if (!$persona) {
            Telegram::sendMessage($this->user->telegram_chat_id, 
                'No persona configured. Please set up in admin dashboard.');
            return;
        }
        
        // STEP 3: FETCH CONVERSATION HISTORY (last 20 messages)
        $chatHistory = Message::where('persona_id', $persona->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse();
        
        // STEP 4: GET MEMORY TAGS
        $memoryTags = $persona->memoryTags;
        
        // STEP 5: GENERATE AI RESPONSE (multimodal if imagePath present)
        $response = GeminiBrain::generateChatResponse(
            $chatHistory,
            $memoryTags,
            $persona->system_prompt,
            $persona,
            $this->imagePath // null for text-only, temp path for vision
        );
        
        // STEP 6: PROCESS MEDIA TAGS & SEND
        $this->sendResponseToTelegram($response);
        
        // STEP 7: CLEANUP TEMP IMAGE FILE
        if ($this->imagePath && file_exists($this->imagePath)) {
            unlink($this->imagePath);
            Log::info('Temp image file deleted', ['path' => $this->imagePath]);
        }
        
        Log::info('Response sent successfully', [
            'user_id' => $this->user->id,
            'had_image' => $this->imagePath !== null,
        ]);
        
    } catch (\Exception $e) {
        Log::error('ProcessChatResponse failed', [
            'user_id' => $this->user->id,
            'error' => $e->getMessage(),
        ]);
        
        // Send user-friendly error
        Telegram::sendMessage(
            $this->user->telegram_chat_id,
            "Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭"
        );
    }
}

private function sendResponseToTelegram(string $response): void
{
    $chatId = $this->user->telegram_chat_id;
    
    // Detect [IMAGE: url] tag
    if (preg_match('/\[IMAGE:\s*([^\]]+)\]/', $response, $imageMatch)) {
        $imageUrl = trim($imageMatch[1]);
        $caption = trim(str_replace($imageMatch[0], '', $response));
        
        Telegram::sendChatAction($chatId, 'upload_photo');
        Telegram::sendPhoto($chatId, $imageUrl, $caption);
        
        // CRITICAL: Save bot message to DB for context continuity
        Message::create([
            'user_id' => $this->user->id,
            'persona_id' => $this->user->persona->id,
            'sender_type' => 'bot',
            'content' => $caption,
            'image_path' => $imageUrl,
        ]);
        
        return;
    }
    
    // Detect [AUDIO: url] tag
    if (preg_match('/\[AUDIO:\s*([^\]]+)\]/', $response, $audioMatch)) {
        $audioUrl = trim($audioMatch[1]);
        $textContent = trim(str_replace($audioMatch[0], '', $response));
        
        Telegram::sendChatAction($chatId, 'record_voice');
        Telegram::sendVoice($chatId, $audioUrl);
        
        // Send text separately if present
        if (!empty($textContent)) {
            Telegram::sendMessage($chatId, $textContent);
        }
        
        // Save bot message
        Message::create([
            'user_id' => $this->user->id,
            'persona_id' => $this->user->persona->id,
            'sender_type' => 'bot',
            'content' => $textContent ?: '[Sent voice message]',
        ]);
        
        return;
    }
    
    // Plain text response
    Telegram::sendMessage($chatId, $response);
    
    // Save bot message
    Message::create([
        'user_id' => $this->user->id,
        'persona_id' => $this->user->persona->id,
        'sender_type' => 'bot',
        'content' => $response,
    ]);
}
```

**Critical Rules:**
1. **Always save bot messages** to DB (even if media-only) for context continuity
2. **Always cleanup temp files** after processing (prevent storage bloat)
3. **Send appropriate chat actions** (`typing`, `upload_photo`, `record_voice`) for UX

**Error Handling:** Malaysian-style friendly messages ("Adoi, ada masalah sikit...") instead of technical errors to users.

---

### 3.3 Polling Mode (Development Alternative)

**Command:** `php artisan telegram:poll`  
**Purpose:** Avoid ngrok webhook issues during development.

```php
// app/Console/Commands/TelegramPolling.php
public function handle(): int
{
    Telegram::removeWebhook(); // Clear any existing webhook
    
    while (true) {
        $updates = Telegram::getUpdates([
            'offset' => $this->lastUpdateId + 1,
            'timeout' => 30, // Long polling
        ]);
        
        foreach ($updates as $update) {
            $this->processUpdate($update->toArray());
            $this->lastUpdateId = $update['update_id'];
        }
    }
}

private function processUpdate(array $update): void
{
    // Same logic as webhook controller:
    // 1. Photo detection & download
    // 2. User resolution
    // 3. Save message
    // 4. Dispatch ProcessChatResponse job with imagePath
    // 5. Trigger memory extraction every 10th message
}
```

**Usage:**
```bash
# Terminal 1: Start polling
php artisan telegram:poll

# Terminal 2: Start queue worker
php artisan queue:work --tries=3
```

---

## 4. Admin Interface (Livewire Components)

All admin pages require `auth` + `verified` middleware.

### Component Overview

#### `Dashboard.php`
**Route:** `/dashboard`  
**Features:**
- System status overview (persona active, last interaction)
- Manual "Wake Up" trigger button (dispatches wake event immediately)
- Today's scheduled events summary

---

#### `PersonaManager.php`
**Route:** `/persona`  
**Features:**
- Edit system prompt (personality instructions for Gemini)
- Edit physical traits (base appearance for image generation)
- Upload avatar image (uses `WithFileUploads` trait)
- Set wake/sleep times (daily schedule boundaries)
- Configure media frequencies (voice_frequency, image_frequency dropdowns)

**Key Method:**
```php
public function save(): void
{
    $this->validate([
        'system_prompt' => 'required|min:50',
        'physical_traits' => 'required',
        'wake_time' => 'required|date_format:H:i',
        'sleep_time' => 'required|date_format:H:i',
        'avatar' => 'nullable|image|max:2048',
    ]);
    
    if ($this->avatar) {
        $path = $this->avatar->store('avatars', 'public');
        $this->persona->avatar_ref_path = $path;
    }
    
    $this->persona->update([...]);
}
```

---

#### `MemoryBrain.php`
**Route:** `/memory`  
**Features:**
- CRUD for memory tags (target, category, value, context)
- Modal forms for add/edit operations
- Real-time search/filter by target or category

**Key Methods:**
```php
public function addTag(): void
{
    MemoryTag::create([
        'persona_id' => $this->personaId,
        'target' => $this->tagTarget,
        'category' => $this->tagCategory,
        'value' => $this->tagValue,
        'context' => $this->tagContext,
    ]);
}

public function deleteTag(int $tagId): void
{
    MemoryTag::find($tagId)?->delete();
}
```

---

#### `ScheduleTimeline.php`
**Route:** `/schedule`  
**Features:**
- Display today's events with status badges
- Cancel pending events
- "Send Now" button (bypasses schedule, sends immediately)
- Auto-refresh with `wire:poll.3s`

**Key Method:**
```php
public function sendNow(int $eventId): void
{
    $event = EventSchedule::find($eventId);
    
    if ($event && $event->status === 'pending') {
        $response = GeminiBrain::generateChatResponse(...);
        Telegram::sendMessage($chatId, $response);
        
        $event->update(['status' => 'sent']);
        $this->dispatch('event-sent');
    }
}
```

---

#### `ChatLogs.php`
**Route:** `/chat-logs`  
**Features:**
- Display message history (user + bot messages)
- Filter by sender_type
- Show image thumbnails for messages with `image_path`
- Infinite scroll with lazy loading

---

#### `TestChat.php`
**Route:** `/test-chat`  
**Features:**
- Live AI chat tester (bypasses Telegram, direct browser interaction)
- Event simulation buttons (test daily plan generation, memory extraction)
- Real-time response streaming (via Livewire events)

**Key Method:**
```php
public function sendMessage(): void
{
    $persona = auth()->user()->persona;
    
    // Build fake chat history
    $chatHistory = collect([
        ['role' => 'user', 'content' => $this->message],
    ]);
    
    $response = GeminiBrain::generateTestResponse(
        $persona,
        $this->message,
        $chatHistory->toArray()
    );
    
    $this->messages[] = ['sender' => 'bot', 'content' => $response];
    $this->dispatch('chat-message-sent'); // Trigger scroll to bottom
}
```

---

## 5. Configuration Structure

### `config/services.php`

```php
return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
    
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'), // HTTPS only in production
    ],
    
    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],
    
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'), // Per-persona voice selection
    ],
];
```

### `config/telegram.php` (Telegram Bot SDK)

```php
return [
    'default' => 'default',
    
    'bots' => [
        'default' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
        ],
    ],
];
```

### Environment Variables (`.env`)

```bash
# AI Services
GEMINI_API_KEY=your_gemini_api_key
CLOUDFLARE_ACCOUNT_ID=your_cloudflare_account_id
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token
ELEVENLABS_API_KEY=your_elevenlabs_api_key
ELEVENLABS_VOICE_ID=21m00Tcm4TlvDq8ikWAM

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_WEBHOOK_URL=https://your-domain.com/api/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your_secret_token_for_validation
TELEGRAM_ADMIN_ID=527908393 # Chat ID of authorized user

# Queue
QUEUE_CONNECTION=database
```

---

## 6. Data Flow Summary

### User Sends Text Message

```
1. Telegram → Webhook → TelegramWebhookController
2. Validate secret token (abort 403 if invalid)
3. Check admin ID (ignore if not admin)
4. Send typing indicator
5. Save Message (sender_type='user')
6. Update last_interaction_at
7. Dispatch ProcessChatResponse job
8. → Job: Fetch history + memory tags
9. → Job: Call GeminiBrain::generateChatResponse (text-only)
10. → Job: Process [IMAGE:] / [AUDIO:] tags
11. → Job: Send to Telegram
12. → Job: Save bot message to DB
13. Return 200 OK to Telegram
```

### User Sends Image

```
1-3. [Same as text flow]
4. Detect photo in update payload
5. Call Telegram::getFile to get file_path
6. Download via https://api.telegram.org/file/bot{token}/{file_path}
7. Save to storage/app/private/temp/{uuid}.jpg
8. Pass imagePath to ProcessChatResponse::dispatch($user, $message, $imagePath)
9. → Job: Call GeminiBrain::generateChatResponse with $imagePath
10. → Job: Encode image as base64
11. → Job: POST to Gemini HTTP API with multimodal payload:
    {
      "contents": [{
        "parts": [
          {"text": "{prompt}"},
          {"inline_data": {"mime_type": "image/jpeg", "data": "{base64}"}}
        ]
      }]
    }
12. → Job: Process response (vision-aware context)
13. → Job: Send to Telegram
14. → Job: Delete temp file with unlink($imagePath)
15. → Job: Save bot message to DB
```

### Scheduled Event Triggers

```
1. Cron runs ProcessScheduledEvents command every minute
2. Fetch events where status='pending' AND scheduled_at <= now()
3. For each event:
   a. Check if user is active (last_interaction_at < 15 min ago)
   b. If active: Reschedule +30 minutes, set status='rescheduled'
   c. If idle: Generate response with GeminiBrain, send to Telegram, set status='sent'
4. Save event message to DB with is_event_trigger=true
```

### Daily Plan Generation

```
1. Cron runs GenerateDailyPlan command at wake_time
2. Fetch persona's memory tags
3. Call GeminiBrain::generateDailyPlan(memoryTags, system_prompt, wake_time, sleep_time)
4. Gemini generates JSON:
   {
     "events": [
       {"time": "09:00", "type": "text", "content": "Good morning! ☀️"},
       {"time": "12:00", "type": "image_generation", "content": "Lunch break selfie at cafe"},
       ...
     ],
     "daily_outfit": "Blue dress with white sneakers",
     "night_outfit": "Pink pajamas"
   }
5. Create EventSchedule records for each event
6. Save outfit memory tags (category='daily_outfit'/'night_outfit')
7. Log plan summary
```

### Memory Extraction

```
1. Triggered every 10th user message
2. Dispatch ExtractMemoryTags job
3. → Job: Fetch last 10 messages
4. → Job: Call GeminiBrain::extractMemoryTags(messages, persona)
5. → Job: Gemini analyzes conversation, returns JSON array of tags
6. → Job: Validate required fields (target, category, value)
7. → Job: Create MemoryTag records
8. → Job: Log extraction summary
```

---

## 7. Critical Technical Notes

### Multimodal Vision Implementation

**Problem:** Gemini PHP SDK has compatibility issues with multimodal content structure (`UnhandledMatchError` in `Content.php`).

**Solution:** Bypass SDK and use HTTP API directly for image inputs:

```php
// Direct HTTP approach (works)
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => base64_encode(file_get_contents($imagePath)),
                ]],
            ],
        ],
    ],
];

$response = Http::timeout(60)->post($url, $payload);
$text = $response->json()['candidates'][0]['content']['parts'][0]['text'];
```

**Temp File Management:**
- Photos stored in `storage/app/private/temp/` (not public)
- Cleanup happens in `ProcessChatResponse::handle()` after processing
- Use `unlink()` to delete temp file, log deletion

### Telegram Media Uploads

**Problem:** Telegram SDK requires local file paths, not URLs.

**Solution:** Convert public URLs to local paths before upload:

```php
// Convert /storage/... URL to storage/app/public/... path
$relativePath = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
$localPath = storage_path('app/public/' . $relativePath);

// Wrap in InputFile for SDK
Telegram::sendPhoto($chatId, InputFile::create($localPath), $caption);
```

### Error Philosophy

- **Never throw exceptions to UI** - return friendly messages
- **Always log with context** - include user_id, file paths, API responses
- **Use exponential backoff** - 3 retries with 1s→2s→4s delays for rate limits
- **Fallback gracefully** - generic safe prompts for NSFW flags, default responses for API failures

---

## 8. Development Workflow

### Initial Setup

```bash
# Install dependencies
composer install
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Link public storage
php artisan storage:link

# Create admin user
php artisan app:create-admin
```

### Development Server

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work --tries=3

# Terminal 3: Log monitoring
php artisan pail

# Terminal 4: Telegram polling (development mode)
php artisan telegram:poll

# Terminal 5: Frontend dev server (if using Vite watch)
npm run dev
```

### Production Deployment

```bash
# Set webhook URL
php artisan telegram:webhook

# Verify webhook status
curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo

# Run queue worker as daemon
supervisor or systemd service with:
php artisan queue:work --tries=3 --timeout=60

# Setup cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Testing Services (Tinker)

```php
// Test Gemini text generation
use App\Facades\GeminiBrain;
$persona = App\Models\Persona::first();
$response = GeminiBrain::generateTestResponse($persona, "Hello!", []);

// Test Gemini vision (with image)
$imagePath = storage_path('app/private/temp/test.jpg');
$response = GeminiBrain::generateChatResponse(
    collect([]), 
    collect([]), 
    $persona->system_prompt, 
    $persona, 
    $imagePath
);

// Test image generation
$imageUrl = GeminiBrain::generateImage("Drinking coffee at cafe", $persona);

// Test Telegram message
use App\Facades\Telegram;
Telegram::sendMessage(527908393, "Test message");

// Test memory extraction
$messages = App\Models\Message::latest()->limit(10)->get();
$tags = GeminiBrain::extractMemoryTags($messages, $persona);
```

---

## 10. Known Limitations & Future Enhancements

### Current Limitations

1. **Single User System:** Only one admin chat ID supported (TELEGRAM_ADMIN_ID)
2. **No Message Editing:** Bot messages can't be edited/recalled after sending
3. **No Conversation Branching:** Linear conversation flow only
4. **Memory Cap:** No automatic pruning of old memory tags (grows indefinitely)
5. **Image Storage:** No cleanup of old generated images (manual purge needed)

### Planned Enhancements

1. **Multi-User Support:** Multiple users with separate personas
2. **Voice Input:** Telegram voice message → Whisper API transcription → Gemini processing
3. **Conversation Threading:** Branch conversations based on context switches
4. **Memory Summarization:** Periodic compression of old memory tags
5. **Media Cleanup:** Automatic deletion of generated images older than 30 days
6. **Webhook Reliability:** Retry mechanism for failed Telegram API calls
7. **Admin Notifications:** Slack/Discord alerts for system errors

---

## Conclusion

This system implements a sophisticated AI companion with:
- **Multimodal AI:** Text + vision (Gemini 2.5 Flash), image generation (Cloudflare Flux), voice synthesis (ElevenLabs)
- **Intelligent Scheduling:** SmartQueue prevents interruptions during active conversations
- **Dynamic Memory:** Automatic extraction and context building from conversations
- **Time-Aware Behavior:** Day/night outfit cycles, wake/sleep schedules
- **Secure Communication:** Secret token validation, admin-only gate, HTTPS webhooks
- **Robust Error Handling:** Exponential backoff, graceful fallbacks, user-friendly messages

The architecture follows Laravel best practices with service-layer abstraction, queue-based async processing, and Livewire for reactive admin interfaces. The "brain" (GeminiBrainService) centralizes all AI logic, making it easy to swap models or add new capabilities.

**Key Innovation:** The hybrid physical traits system combines static `physical_traits` with dynamic `daily_outfit`/`night_outfit` memory tags to create consistent yet varied image generation prompts that adapt to time of day.
