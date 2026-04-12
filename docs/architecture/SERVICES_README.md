# Service Layer Documentation

> Last updated: April 2026. Reflects migration from google-gemini-php SDK to Laravel AI SDK.

## Overview

Business logic lives in **services**, not controllers. All services are singletons registered in `AppServiceProvider` and accessed via facades or dependency injection.

| Service | Facade | File |
|---|---|---|
| `BrainService` | `Brain::` | `app/Services/BrainService.php` |
| `TelegramService` | `Telegram::` | `app/Services/TelegramService.php` |
| `SmartQueueService` | `SmartQueue::` | `app/Services/SmartQueueService.php` |
| `WardrobeService` | `Wardrobe::` | `app/Services/WardrobeService.php` |
| `AudioService` | — (injected) | `app/Services/AudioService.php` |
| `ImageGeneratorManager` | — (injected) | `app/Services/ImageGeneratorManager.php` |

---

## 1. BrainService — AI Orchestration

**Facade:** `Brain::`
**File:** `app/Services/BrainService.php`

The central AI service. Wraps all agent calls through the **Laravel AI SDK** (`laravel/ai`). No direct dependency on any specific AI provider — the provider and model are fully configurable per-agent via `config/ai.php`.

### Constructor

```php
public function __construct(private readonly ImageGeneratorManager $imageGeneratorManager)
```

### Public Methods

#### `generateChatResponse(Collection $chatHistory, Collection $memoryTags, string $systemPrompt, Persona $persona): string`

Generates a live conversational response via `PersonaAgent`. Builds full context (memory, wardrobe, mood) and passes it through the agent's instructions.

- Chat history is given as prior messages
- Latest user message is extracted and used as the prompt
- Response is passed through `processMediaTags()` to handle `[GENERATE_IMAGE:]` and `[SEND_VOICE:]` tags

```php
$response = Brain::generateChatResponse(
    $chatHistory,      // Collection of Message models
    $persona->memoryTags,
    $persona->system_prompt,
    $persona
);
```

---

#### `generateTestResponse(Persona $persona, string $userMessage, array $chatHistory = []): string`

Lightweight version for the admin TestChat panel. Does not save to the database. Accepts raw array history instead of Message models.

```php
$response = Brain::generateTestResponse($persona, 'Hello!', $chatHistory);
```

---

#### `generate(string $prompt): string`

General-purpose single-prompt generation via an anonymous utility agent. Used for optimization prompts (e.g. persona prompt rewriting in `PersonaManager`).

```php
$optimized = Brain::generate($rawPrompt);
```

---

#### `generateEventResponse(EventSchedule $event, Persona $persona): string`

Generates a just-in-time message for a scheduled event. Treats `event->context_prompt` as an instruction (e.g. "Send morning greeting"), not final text. The AI interprets and writes the actual message.

- Includes recent conversation history, memory context, and mood
- Response may contain `<SPLIT>` markers for multi-message sends
- Response always ends with `[MOOD: value]` for tracking

```php
$reply = Brain::generateEventResponse($event, $persona);
```

---

#### `generateDailyPlan(Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime): array`

Generates a daily schedule as a JSON array of event instructions via `DailyPlanAgent`. Returns a plain array of events (no outfit data — outfit is now owned by `WardrobeService`).

**Returns:**
```php
[
    ['type' => 'text', 'content' => 'Send morning greeting. Ask how they slept.', 'scheduled_at' => '2026-04-12 08:00:00'],
    ['type' => 'image_generation', 'content' => 'Generate cozy café selfie.', 'scheduled_at' => '2026-04-12 14:00:00'],
]
```

```php
$events = Brain::generateDailyPlan(
    $persona->memoryTags,
    $persona->system_prompt,
    $persona->wake_time,   // '08:00'
    $persona->sleep_time   // '23:00'
);
```

---

#### `extractMemoryTags(Collection $chatHistory, Persona $persona): array`

Analyses recent conversation and returns a structured diff of memory tags to add, update, or remove via `MemoryExtractionAgent`.

**Returns:**
```php
[
    'add'    => [['target' => 'user', 'category' => 'music', 'value' => 'likes Linkin Park']],
    'update' => [['id' => 42, 'value' => 'new value']],
    'remove' => [['id' => 55]],
]
```

---

#### `generateImage(string $prompt, Persona $persona): ?string`

Generates an image via `ImageGeneratorManager`. Automatically builds a full prompt with `physical_traits`, dynamic memory traits, and today's wardrobe outfit. Returns the public URL or `null` on failure.

```php
$url = Brain::generateImage('selfie at the beach', $persona);
```

---

#### `buildPersonaInstructions(Persona $persona): string`

Builds the full system instructions string used by `PersonaAgent`. Combines:
- `system_prompt` from the persona
- Memory context (user facts + self facts)
- Current wardrobe outfit (from `WardrobeService`)
- Media instructions (`[GENERATE_IMAGE:]`, `[SEND_VOICE:]` formatting)
- Mood tracking rules
- Time/date context

Used internally by `PersonaAgent::instructions()`.

---

#### `processMediaTags(string $text, Persona $persona): string`

Processes AI response text, replacing media tags with actual generated content:
- `[GENERATE_IMAGE: description]` → calls image generator → replaces with `[IMAGE: url]`
- `[SEND_VOICE: text]` → calls `AudioService` → replaces with `[AUDIO: url]`

```php
$processed = Brain::processMediaTags($rawAiResponse, $persona);
```

---

#### `generateImageLoadingMessage(Persona $persona): ?string`

Generates a short "loading" message the persona says while an image is being generated (e.g. "Give me a sec to get ready 📸"). Returns `null` if generation fails.

---

#### `setCurrentPersona(Persona $persona): void`

Sets the persona context for use during image prompt building within the same request cycle.

---

### Configuration (per-agent)

Agents read their provider/model from `config/ai.php`:

```php
// config/ai.php
'agents' => [
    'default_model' => env('AI_DEFAULT_MODEL', 'gemma-4-e2b-it-uncensored'),
    'chat'    => ['provider' => env('AI_CHAT_PROVIDER'),    'model' => env('AI_CHAT_MODEL')],
    'planner' => ['provider' => env('AI_PLANNER_PROVIDER'), 'model' => env('AI_PLANNER_MODEL')],
    'memory'  => ['provider' => env('AI_MEMORY_PROVIDER'),  'model' => env('AI_MEMORY_MODEL')],
    'event'   => ['provider' => env('AI_EVENT_PROVIDER'),   'model' => env('AI_EVENT_MODEL')],
    'utility' => ['provider' => env('AI_UTILITY_PROVIDER'), 'model' => env('AI_UTILITY_MODEL')],
]
```

---

## 2. TelegramService — Messaging

**Facade:** `Telegram::`
**File:** `app/Services/TelegramService.php`

Wraps the Telegram Bot API. Supports multi-bot via optional `$botToken` parameter on each method.

### Key Methods

| Method | Description |
|---|---|
| `sendMessage($chatId, $message, $botToken, $options)` | Send HTML text message |
| `sendStreamingMessage($chatId, $message, $botToken)` | Send with typing indicator simulation |
| `sendPhoto($chatId, $photo, $caption, $botToken, $options)` | Send image (URL or file_id) |
| `sendVoice($chatId, $voice, $botToken, $options)` | Send audio file |
| `sendChatAction($chatId, $action, $botToken)` | Show typing/uploading status |
| `sendAndEditMessage($chatId, $initial, $final)` | Send loading message then edit to final |
| `setWebhook($url)` | Register webhook URL |
| `removeWebhook()` | Remove webhook |
| `getMe()` | Get bot info |
| `getWebhookInfo()` | Get current webhook status |
| `getUpdates($params)` | Poll for updates (non-webhook mode) |
| `parseUpdate($update)` | Parse incoming webhook payload |
| `downloadFile($fileId)` | Download file from Telegram servers |
| `getFile($params)` | Get file info by file_id |
| `setToken($token)` | Switch active bot token at runtime |

### Example

```php
Telegram::sendStreamingMessage($user->telegram_chat_id, $aiResponse);
Telegram::sendPhoto($user->telegram_chat_id, $imageUrl, 'Look at this! 📸');
```

---

## 3. SmartQueueService — Intelligent Scheduling

**Facade:** `SmartQueue::`
**File:** `app/Services/SmartQueueService.php`

Prevents events from interrupting active conversations. If the user has interacted within the active window (default: 15 min), the event is rescheduled (default: +30 min). Thresholds are configurable via `config/services.php`.

### Key Methods

| Method | Description |
|---|---|
| `isUserActive(User $user): bool` | True if user interacted within active window |
| `shouldExecuteEvent(EventSchedule $event): bool` | True if event should run now |
| `processEvent($event, callable $callback): bool` | Run or reschedule, returns true if executed |
| `rescheduleEvent($event, $delayMinutes): void` | Delay event, status → 'rescheduled' |
| `getDueEvents()` | Pending events past their scheduled_at |
| `getRescheduledDueEvents()` | Rescheduled events now due |
| `updateUserInteraction(User $user): void` | Stamp last_interaction_at = now() |
| `isWithinActiveHours($persona): bool` | Between persona wake_time and sleep_time |

### Example

```php
SmartQueue::processEvent($event, function ($event) use ($persona) {
    $reply = Brain::generateEventResponse($event, $persona);
    Telegram::sendMessage($persona->user->telegram_chat_id, $reply);
    $event->update(['status' => 'sent']);
});
```

---

## 4. WardrobeService — Outfit Management

**Facade:** `Wardrobe::`
**File:** `app/Services/WardrobeService.php`

Manages the persona's wardrobe and daily outfit selection. Outfits are stored as `WardrobeItem` models — no longer in `memory_tags`. The AI sees today's outfit via `buildMemoryContext()` in `BrainService`.

### Slot Names

| Time Context | Slot Name |
|---|---|
| `'daytime'` | `casual_daytime` |
| `'nighttime'` | `casual_nighttime` |

### Key Methods

| Method | Description |
|---|---|
| `getTodaysOutfit(Persona $persona, string $timeContext): ?WardrobeItem` | Get (or select) today's outfit for a time slot |
| `selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem` | 70/30 rotation logic, never repeats yesterday |
| `buildOutfitDescription(WardrobeItem $item, string $shotType): string` | Filter description for image prompt by shot type |
| `setOutfit(int $personaId, string $slot, array $parts, bool $isPrimary): WardrobeItem` | Create or replace an outfit item |
| `generateOutfits(Persona $persona, string $slot, ...): Collection` | AI-generate new outfit items via Gemini |
| `generateSimilarOutfits(WardrobeItem $existing, int $count): Collection` | Generate variations of an existing outfit |
| `getOutfitHistory(int $personaId, int $days): Collection` | Recent daily outfit selections |
| `getCurrentTimeContext(): string` | Returns `'daytime'` or `'nighttime'` based on current hour |
| `getDefaultTags(Persona $persona): array` | Infer style tags from persona's system_prompt |
| `checkGenerationLimit(Persona $persona): bool` | True if under daily generation quota |

### Rotation Logic

- **70%** chance: use the primary outfit (`is_primary = true`)
- **30%** chance: random from rotation pool
- Never repeats the previous day's outfit
- If no outfits exist → auto-generates a fallback via AI

### Clearing Cache

To force new outfit selection today, delete from `daily_outfit_selections`:

```php
DB::table('daily_outfit_selections')->delete();
```

---

## 5. AudioService — Voice Synthesis

**File:** `app/Services/AudioService.php`
**No facade** — injected or resolved via `app(AudioService::class)`.

Calls ElevenLabs API to convert text to MP3 audio. Stores result via Spatie MediaLibrary attached to the Persona model (`voice_notes` collection), with fallback to `Storage`.

### Method

```php
generateVoice(string $text, ?Persona $persona = null, ?string $voiceId = null): ?string
```

Returns the public URL of the generated audio file, or `null` on failure.

```php
$audioUrl = app(AudioService::class)->generateVoice('Hey sayang!', $persona);
```

**Config:**
```env
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_ID=
```

---

## 6. ImageGeneratorManager — Multi-Driver Image Generation

**File:** `app/Services/ImageGeneratorManager.php`
**No facade** — injected into `BrainService` constructor.

Selects and returns the correct image generator driver based on `config/services.php`.

### Drivers

| Driver Key | Class | Model | Notes |
|---|---|---|---|
| `cloudflare` (default) | `CloudflareFluxDriver` | `@cf/black-forest-labs/flux-1-schnell` | 4-step generation, NSFW fallback |
| `kie_ai_text_to_image` | `KieAiTextToImageDriver` | `bytedance/seedream-v4-text-to-image` | Async polling, 2 min timeout |
| `kie_ai_edit` | `KieAiEditDriver` | `bytedance/seedream-v4-edit` | Uses persona reference images |

### Usage

```php
$driver = app(ImageGeneratorManager::class)->driver(); // uses default
$driver = app(ImageGeneratorManager::class)->driver('kie_ai_text_to_image');
$imageUrl = $driver->generate($prompt, $persona);
```

**Config:**
```env
IMAGE_GENERATOR_DRIVER=cloudflare   # or kie_ai_text_to_image, kie_ai_edit
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
KIE_AI_API_KEY=
```

---

## Complete Chat Flow

```
Telegram Webhook
    ↓
SmartQueue::updateUserInteraction($user)
    ↓
ProcessChatResponse (queued Job)
    ↓
Brain::generateChatResponse($history, $memoryTags, $systemPrompt, $persona)
    ↓ (internally)
    PersonaAgent → buildPersonaInstructions()
        → buildMemoryContext()        [memory tags + wardrobe outfit]
        → buildMediaInstructions()    [[GENERATE_IMAGE:] / [SEND_VOICE:] rules]
        ↓
    AI response (may contain media tags + <SPLIT> + [MOOD:])
    ↓
processMediaTags()
    → [GENERATE_IMAGE: ...] → ImageGeneratorManager → MediaLibrary → [IMAGE: url]
    → [SEND_VOICE: ...]     → AudioService          → MediaLibrary → [AUDIO: url]
    ↓
Telegram::sendMessage / sendPhoto / sendVoice
    ↓
ExtractMemoryTags (background Job, every N messages)
    ↓
Brain::extractMemoryTags() → MemoryTag upsert/delete
```ayer Documentation

> Last updated: April 2026. Reflects migration from google-gemini-php SDK to Laravel AI SDK.

## Overview

Business logic lives in **services**, not controllers. All services are singletons registered in `AppServiceProvider` and accessed via facades or dependency injection.

| Service | Facade | File |
|---|---|---|
| `BrainService` | `Brain::` | `app/Services/BrainService.php` |
| `TelegramService` | `Telegram::` | `app/Services/TelegramService.php` |
| `SmartQueueService` | `SmartQueue::` | `app/Services/SmartQueueService.php` |
| `WardrobeService` | `Wardrobe::` | `app/Services/WardrobeService.php` |
| `AudioService` | — (injected) | `app/Services/AudioService.php` |
| `ImageGeneratorManager` | — (injected) | `app/Services/ImageGeneratorManager.php` |

---

## 1. BrainService — AI Orchestration

**Facade:** `Brain::`
**File:** `app/Services/BrainService.php`

The central AI service. Wraps all agent calls through the **Laravel AI SDK** (`laravel/ai`). No direct dependency on any specific AI provider — the provider and model are fully configurable per-agent via `config/ai.php`.

### Constructor

```php
public function __construct(private readonly ImageGeneratorManager $imageGeneratorManager)
```

### Public Methods

#### `generateChatResponse(Collection $chatHistory, Collection $memoryTags, string $systemPrompt, Persona $persona): string`

Generates a live conversational response via `PersonaAgent`. Builds full context (memory, wardrobe, mood) and passes it through the agent's instructions.

- Chat history is given as prior messages
- Latest user message is extracted and used as the prompt
- Response is passed through `processMediaTags()` to handle `[GENERATE_IMAGE:]` and `[SEND_VOICE:]` tags

```php
$response = Brain::generateChatResponse(
    $chatHistory,      // Collection of Message models
    $persona->memoryTags,
    $persona->system_prompt,
    $persona
);
```

---

#### `generateTestResponse(Persona $persona, string $userMessage, array $chatHistory = []): string`

Lightweight version for the admin TestChat panel. Does not save to the database. Accepts raw array history instead of Message models.

```php
$response = Brain::generateTestResponse($persona, 'Hello!', $chatHistory);
```

---

#### `generate(string $prompt): string`

General-purpose single-prompt generation via an anonymous utility agent. Used for optimization prompts (e.g. persona prompt rewriting in `PersonaManager`).

```php
$optimized = Brain::generate($rawPrompt);
```

---

#### `generateEventResponse(EventSchedule $event, Persona $persona): string`

Generates a just-in-time message for a scheduled event. Treats `event->context_prompt` as an instruction (e.g. "Send morning greeting"), not final text. The AI interprets and writes the actual message.

- Includes recent conversation history, memory context, and mood
- Response may contain `<SPLIT>` markers for multi-message sends
- Response always ends with `[MOOD: value]` for tracking

```php
$reply = Brain::generateEventResponse($event, $persona);
```

---

#### `generateDailyPlan(Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime): array`

Generates a daily schedule as a JSON array of event instructions via `DailyPlanAgent`. Returns a plain array of events (no outfit data — outfit is now owned by `WardrobeService`).

**Returns:**
```php
[
    ['type' => 'text', 'content' => 'Send morning greeting. Ask how they slept.', 'scheduled_at' => '2026-04-12 08:00:00'],
    ['type' => 'image_generation', 'content' => 'Generate cozy café selfie.', 'scheduled_at' => '2026-04-12 14:00:00'],
]
```

```php
$events = Brain::generateDailyPlan(
    $persona->memoryTags,
    $persona->system_prompt,
    $persona->wake_time,   // '08:00'
    $persona->sleep_time   // '23:00'
);
```

---

#### `extractMemoryTags(Collection $chatHistory, Persona $persona): array`

Analyses recent conversation and returns a structured diff of memory tags to add, update, or remove via `MemoryExtractionAgent`.

**Returns:**
```php
[
    'add'    => [['target' => 'user', 'category' => 'music', 'value' => 'likes Linkin Park']],
    'update' => [['id' => 42, 'value' => 'new value']],
    'remove' => [['id' => 55]],
]
```

---

#### `generateImage(string $prompt, Persona $persona): ?string`

Generates an image via `ImageGeneratorManager`. Automatically builds a full prompt with `physical_traits`, dynamic memory traits, and today's wardrobe outfit. Returns the public URL or `null` on failure.

```php
$url = Brain::generateImage('selfie at the beach', $persona);
```

---

#### `buildPersonaInstructions(Persona $persona): string`

Builds the full system instructions string used by `PersonaAgent`. Combines:
- `system_prompt` from the persona
- Memory context (user facts + self facts)
- Current wardrobe outfit (from `WardrobeService`)
- Media instructions (`[GENERATE_IMAGE:]`, `[SEND_VOICE:]` formatting)
- Mood tracking rules
- Time/date context

Used internally by `PersonaAgent::instructions()`.

---

#### `processMediaTags(string $text, Persona $persona): string`

Processes AI response text, replacing media tags with actual generated content:
- `[GENERATE_IMAGE: description]` → calls image generator → replaces with `[IMAGE: url]`
- `[SEND_VOICE: text]` → calls `AudioService` → replaces with `[AUDIO: url]`

```php
$processed = Brain::processMediaTags($rawAiResponse, $persona);
```

---

#### `generateImageLoadingMessage(Persona $persona): ?string`

Generates a short "loading" message the persona says while an image is being generated (e.g. "Give me a sec to get ready 📸"). Returns `null` if generation fails.

---

#### `setCurrentPersona(Persona $persona): void`

Sets the persona context for use during image prompt building within the same request cycle.

---

### Configuration (per-agent)

Agents read their provider/model from `config/ai.php`:

```php
// config/ai.php
'agents' => [
    'default_model' => env('AI_DEFAULT_MODEL', 'gemma-4-e2b-it-uncensored'),
    'chat'    => ['provider' => env('AI_CHAT_PROVIDER'),    'model' => env('AI_CHAT_MODEL')],
    'planner' => ['provider' => env('AI_PLANNER_PROVIDER'), 'model' => env('AI_PLANNER_MODEL')],
    'memory'  => ['provider' => env('AI_MEMORY_PROVIDER'),  'model' => env('AI_MEMORY_MODEL')],
    'event'   => ['provider' => env('AI_EVENT_PROVIDER'),   'model' => env('AI_EVENT_MODEL')],
    'utility' => ['provider' => env('AI_UTILITY_PROVIDER'), 'model' => env('AI_UTILITY_MODEL')],
]
```

---

## 2. TelegramService — Messaging

**Facade:** `Telegram::`
**File:** `app/Services/TelegramService.php`

Wraps the Telegram Bot API. Supports multi-bot via optional `$botToken` parameter on each method.

### Key Methods

| Method | Description |
|---|---|
| `sendMessage($chatId, $message, $botToken, $options)` | Send HTML text message |
| `sendStreamingMessage($chatId, $message, $botToken)` | Send with typing indicator simulation |
| `sendPhoto($chatId, $photo, $caption, $botToken, $options)` | Send image (URL or file_id) |
| `sendVoice($chatId, $voice, $botToken, $options)` | Send audio file |
| `sendChatAction($chatId, $action, $botToken)` | Show typing/uploading status |
| `sendAndEditMessage($chatId, $initial, $final)` | Send loading message then edit to final |
| `setWebhook($url)` | Register webhook URL |
| `removeWebhook()` | Remove webhook |
| `getMe()` | Get bot info |
| `getWebhookInfo()` | Get current webhook status |
| `getUpdates($params)` | Poll for updates (non-webhook mode) |
| `parseUpdate($update)` | Parse incoming webhook payload |
| `downloadFile($fileId)` | Download file from Telegram servers |
| `getFile($params)` | Get file info by file_id |
| `setToken($token)` | Switch active bot token at runtime |

### Example

```php
Telegram::sendStreamingMessage($user->telegram_chat_id, $aiResponse);
Telegram::sendPhoto($user->telegram_chat_id, $imageUrl, 'Look at this! 📸');
```

---

## 3. SmartQueueService — Intelligent Scheduling

**Facade:** `SmartQueue::`
**File:** `app/Services/SmartQueueService.php`

Prevents events from interrupting active conversations. If the user has interacted within the active window (default: 15 min), the event is rescheduled (default: +30 min). Thresholds are configurable via `config/services.php`.

### Key Methods

| Method | Description |
|---|---|
| `isUserActive(User $user): bool` | True if user interacted within active window |
| `shouldExecuteEvent(EventSchedule $event): bool` | True if event should run now |
| `processEvent($event, callable $callback): bool` | Run or reschedule, returns true if executed |
| `rescheduleEvent($event, $delayMinutes): void` | Delay event, status → 'rescheduled' |
| `getDueEvents()` | Pending events past their scheduled_at |
| `getRescheduledDueEvents()` | Rescheduled events now due |
| `updateUserInteraction(User $user): void` | Stamp last_interaction_at = now() |
| `isWithinActiveHours($persona): bool` | Between persona wake_time and sleep_time |

### Example

```php
SmartQueue::processEvent($event, function ($event) use ($persona) {
    $reply = Brain::generateEventResponse($event, $persona);
    Telegram::sendMessage($persona->user->telegram_chat_id, $reply);
    $event->update(['status' => 'sent']);
});
```

---

## 4. WardrobeService — Outfit Management

**Facade:** `Wardrobe::`
**File:** `app/Services/WardrobeService.php`

Manages the persona's wardrobe and daily outfit selection. Outfits are stored as `WardrobeItem` models — no longer in `memory_tags`. The AI sees today's outfit via `buildMemoryContext()` in `BrainService`.

### Slot Names

| Time Context | Slot Name |
|---|---|
| `'daytime'` | `casual_daytime` |
| `'nighttime'` | `casual_nighttime` |

### Key Methods

| Method | Description |
|---|---|
| `getTodaysOutfit(Persona $persona, string $timeContext): ?WardrobeItem` | Get (or select) today's outfit for a time slot |
| `selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem` | 70/30 rotation logic, never repeats yesterday |
| `buildOutfitDescription(WardrobeItem $item, string $shotType): string` | Filter description for image prompt by shot type |
| `setOutfit(int $personaId, string $slot, array $parts, bool $isPrimary): WardrobeItem` | Create or replace an outfit item |
| `generateOutfits(Persona $persona, string $slot, ...): Collection` | AI-generate new outfit items via Gemini |
| `generateSimilarOutfits(WardrobeItem $existing, int $count): Collection` | Generate variations of an existing outfit |
| `getOutfitHistory(int $personaId, int $days): Collection` | Recent daily outfit selections |
| `getCurrentTimeContext(): string` | Returns `'daytime'` or `'nighttime'` based on current hour |
| `getDefaultTags(Persona $persona): array` | Infer style tags from persona's system_prompt |
| `checkGenerationLimit(Persona $persona): bool` | True if under daily generation quota |

### Rotation Logic

- **70%** chance: use the primary outfit (`is_primary = true`)
- **30%** chance: random from rotation pool
- Never repeats the previous day's outfit
- If no outfits exist → auto-generates a fallback via AI

### Clearing Cache

To force new outfit selection today, delete from `daily_outfit_selections`:

```php
DB::table('daily_outfit_selections')->delete();
```

---

## 5. AudioService — Voice Synthesis

**File:** `app/Services/AudioService.php`
**No facade** — injected or resolved via `app(AudioService::class)`.

Calls ElevenLabs API to convert text to MP3 audio. Stores result via Spatie MediaLibrary attached to the Persona model (`voice_notes` collection), with fallback to `Storage`.

### Method

```php
generateVoice(string $text, ?Persona $persona = null, ?string $voiceId = null): ?string
```

Returns the public URL of the generated audio file, or `null` on failure.

```php
$audioUrl = app(AudioService::class)->generateVoice('Hey sayang!', $persona);
```

**Config:**
```env
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_ID=
```

---

## 6. ImageGeneratorManager — Multi-Driver Image Generation

**File:** `app/Services/ImageGeneratorManager.php`
**No facade** — injected into `BrainService` constructor.

Selects and returns the correct image generator driver based on `config/services.php`.

### Drivers

| Driver Key | Class | Model | Notes |
|---|---|---|---|
| `cloudflare` (default) | `CloudflareFluxDriver` | `@cf/black-forest-labs/flux-1-schnell` | 4-step generation, NSFW fallback |
| `kie_ai_text_to_image` | `KieAiTextToImageDriver` | `bytedance/seedream-v4-text-to-image` | Async polling, 2 min timeout |
| `kie_ai_edit` | `KieAiEditDriver` | `bytedance/seedream-v4-edit` | Uses persona reference images |

### Usage

```php
$driver = app(ImageGeneratorManager::class)->driver(); // uses default
$driver = app(ImageGeneratorManager::class)->driver('kie_ai_text_to_image');
$imageUrl = $driver->generate($prompt, $persona);
```

**Config:**
```env
IMAGE_GENERATOR_DRIVER=cloudflare   # or kie_ai_text_to_image, kie_ai_edit
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
KIE_AI_API_KEY=
```

---

## Complete Chat Flow

```
Telegram Webhook
    ↓
SmartQueue::updateUserInteraction($user)
    ↓
ProcessChatResponse (queued Job)
    ↓
Brain::generateChatResponse($history, $memoryTags, $systemPrompt, $persona)
    ↓ (internally)
    PersonaAgent → buildPersonaInstructions()
        → buildMemoryContext()        [memory tags + wardrobe outfit]
        → buildMediaInstructions()    [[GENERATE_IMAGE:] / [SEND_VOICE:] rules]
        ↓
    AI response (may contain media tags + <SPLIT> + [MOOD:])
    ↓
processMediaTags()
    → [GENERATE_IMAGE: ...] → ImageGeneratorManager → MediaLibrary → [IMAGE: url]
    → [SEND_VOICE: ...]     → AudioService          → MediaLibrary → [AUDIO: url]
    ↓
Telegram::sendMessage / sendPhoto / sendVoice
    ↓
ExtractMemoryTags (background Job, every N messages)
    ↓
Brain::extractMemoryTags() → MemoryTag upsert/delete
```
