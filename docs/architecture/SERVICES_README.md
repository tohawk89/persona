# Service Layer Documentation

## Overview

The service layer implements the core business logic for the AI Virtual Companion system. It consists of three main services that handle AI communication, messaging, and intelligent event scheduling.

---

## Services

### 1. GeminiBrainService

**Location:** `app/Services/GeminiBrainService.php`

Handles all communication with the Google Gemini 1.5 Pro API.

#### Methods

##### `generateChatResponse(Collection $chatHistory, Collection $memoryTags, string $systemPrompt): string`

Generates a conversational response based on chat history and stored memory tags.

**Parameters:**
- `$chatHistory` - Collection of Message models with `sender_type` and `content`
- `$memoryTags` - Collection of MemoryTag models with `target`, `key`, `value`
- `$systemPrompt` - The persona's system prompt

**Returns:** The AI's text response

**Example Usage:**
```php
use App\Services\GeminiBrainService;

$brainService = app(GeminiBrainService::class);

$response = $brainService->generateChatResponse(
    chatHistory: Message::where('user_id', $user->id)->latest()->take(20)->get(),
    memoryTags: MemoryTag::where('persona_id', $persona->id)->get(),
    systemPrompt: $persona->system_prompt
);
```

---

##### `generateDailyPlan(Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime): array`

Generates a daily event plan with 5 events spread throughout the day.

**Parameters:**
- `$memoryTags` - Collection of MemoryTag models
- `$systemPrompt` - The persona's system prompt
- `$wakeTime` - Format: "08:00"
- `$sleepTime` - Format: "23:00"

**Returns:** Array of events with structure:
```php
[
    [
        'type' => 'text|image',
        'content' => 'Message or image prompt',
        'scheduled_at' => '2025-11-24 08:00:00'
    ]
]
```

**Example Usage:**
```php
$events = $brainService->generateDailyPlan(
    memoryTags: $persona->memoryTags,
    systemPrompt: $persona->system_prompt,
    wakeTime: $persona->wake_time,
    sleepTime: $persona->sleep_time
);

// Save to database
foreach ($events as $eventData) {
    EventSchedule::create([
        'persona_id' => $persona->id,
        'type' => $eventData['type'],
        'content' => $eventData['content'],
        'scheduled_at' => $eventData['scheduled_at'],
        'status' => 'pending',
    ]);
}
```

---

##### `extractMemoryTags(Collection $chatHistory, string $systemPrompt): array`

Analyzes conversation history and extracts new facts about the user or persona.

**Parameters:**
- `$chatHistory` - Collection of recent messages
- `$systemPrompt` - The persona's system prompt

**Returns:** Array of memory tags:
```php
[
    [
        'target' => 'user|self',
        'key' => 'fact_name',
        'value' => 'fact_value'
    ]
]
```

**Example Usage:**
```php
// Trigger after every 10 messages
if ($messageCount % 10 === 0) {
    $memoryTags = $brainService->extractMemoryTags(
        chatHistory: $recentMessages,
        systemPrompt: $persona->system_prompt
    );
    
    foreach ($memoryTags as $tagData) {
        MemoryTag::create([
            'persona_id' => $persona->id,
            'target' => $tagData['target'],
            'key' => $tagData['key'],
            'value' => $tagData['value'],
        ]);
    }
}
```

---

### 2. TelegramService

**Location:** `app/Services/TelegramService.php`

Manages all interactions with the Telegram Bot API.

#### Methods

##### `sendMessage(string|int $chatId, string $message, array $options = []): bool`

Sends a text message to a Telegram chat.

**Parameters:**
- `$chatId` - Telegram chat ID
- `$message` - Text message (supports HTML)
- `$options` - Additional Telegram API options

**Example Usage:**
```php
use App\Services\TelegramService;

$telegram = app(TelegramService::class);

$telegram->sendMessage(
    chatId: $user->telegram_chat_id,
    message: '<b>Hello!</b> How are you today?'
);
```

---

##### `sendStreamingMessage(string|int $chatId, string $message): bool`

Sends a message with a typing indicator to simulate natural conversation.

**Example Usage:**
```php
$telegram->sendStreamingMessage(
    chatId: $user->telegram_chat_id,
    message: $aiResponse
);
```

---

##### `sendPhoto(string|int $chatId, string $photo, ?string $caption = null, array $options = []): bool`

Sends a photo to a Telegram chat.

**Parameters:**
- `$chatId` - Telegram chat ID
- `$photo` - Photo URL or file_id
- `$caption` - Optional caption (supports HTML)

**Example Usage:**
```php
$telegram->sendPhoto(
    chatId: $user->telegram_chat_id,
    photo: 'https://example.com/image.jpg',
    caption: 'Check out this view! 🌅'
);
```

---

##### `parseUpdate(array $update): array`

Parses incoming webhook data from Telegram.

**Returns:**
```php
[
    'chat_id' => 123456789,
    'message_id' => 1,
    'text' => 'Hello bot!',
    'user' => [
        'id' => 123456789,
        'first_name' => 'John',
        'username' => 'johndoe'
    ],
    'date' => 1700000000
]
```

**Example Usage:**
```php
public function webhook(Request $request)
{
    $telegram = app(TelegramService::class);
    $data = $telegram->parseUpdate($request->all());
    
    // Process the message
}
```

---

### 3. SmartQueueService

**Location:** `app/Services/SmartQueueService.php`

Implements the "Smart Queue" logic to prevent interrupting active conversations.

#### Core Logic

- If user has interacted within **15 minutes**, reschedule event for **+30 minutes**
- Respects persona's wake/sleep times
- Tracks and manages event statuses

#### Methods

##### `isUserActive(User $user): bool`

Checks if a user is currently in an active conversation.

**Example Usage:**
```php
use App\Services\SmartQueueService;

$smartQueue = app(SmartQueueService::class);

if ($smartQueue->isUserActive($user)) {
    // User is chatting, reschedule event
}
```

---

##### `shouldExecuteEvent(EventSchedule $event): bool`

Determines if an event should be executed or rescheduled based on user activity.

**Example Usage:**
```php
$event = EventSchedule::find($eventId);

if ($smartQueue->shouldExecuteEvent($event)) {
    // Execute the event
} else {
    // Event will be automatically rescheduled
}
```

---

##### `processEvent(EventSchedule $event, callable $executeCallback): bool`

Processes an event with smart queue logic, executing or rescheduling as needed.

**Parameters:**
- `$event` - The EventSchedule model
- `$executeCallback` - Function to execute if event should run

**Returns:** `true` if executed, `false` if rescheduled

**Example Usage:**
```php
$smartQueue->processEvent($event, function($event) use ($telegram, $user) {
    if ($event->type === 'text') {
        $telegram->sendMessage($user->telegram_chat_id, $event->content);
    } elseif ($event->type === 'image') {
        $telegram->sendPhoto($user->telegram_chat_id, $event->content);
    }
});
```

---

##### `updateUserInteraction(User $user): void`

Updates the user's `last_interaction_at` timestamp to current time.

**Example Usage:**
```php
// In webhook controller when user sends a message
$smartQueue->updateUserInteraction($user);
```

---

##### `isWithinActiveHours(Persona $persona): bool`

Checks if current time is within the persona's active hours (between wake_time and sleep_time).

**Example Usage:**
```php
if ($smartQueue->isWithinActiveHours($persona)) {
    // Persona is "awake", can send messages
}
```

---

## Data Transfer Objects (DTOs)

### EventDTO

**Location:** `app/DataTransferObjects/EventDTO.php`

Structured data container for events.

**Usage:**
```php
use App\DataTransferObjects\EventDTO;

$event = EventDTO::fromArray([
    'type' => 'text',
    'content' => 'Hello!',
    'scheduled_at' => '2025-11-24 08:00:00'
]);

if ($event->isValid()) {
    // Use the event
}
```

### MemoryTagDTO

**Location:** `app/DataTransferObjects/MemoryTagDTO.php`

Structured data container for memory tags.

**Usage:**
```php
use App\DataTransferObjects\MemoryTagDTO;

$tag = MemoryTagDTO::fromArray([
    'target' => 'user',
    'key' => 'favorite_food',
    'value' => 'pizza'
]);

if ($tag->isValid()) {
    // Save to database
}
```

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
GEMINI_API_KEY=your_gemini_api_key_here
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
```

### Config Files

The services use these configuration files:
- `config/services.php` - API credentials
- `config/telegram.php` - Telegram bot settings

---

## Integration Example

### Complete Chat Flow

```php
use App\Services\{GeminiBrainService, TelegramService, SmartQueueService};
use App\Models\{User, Persona, Message};

// 1. Receive webhook
$telegram = app(TelegramService::class);
$smartQueue = app(SmartQueueService::class);
$brain = app(GeminiBrainService::class);

$data = $telegram->parseUpdate($request->all());

// 2. Update user interaction
$user = User::where('telegram_chat_id', $data['chat_id'])->first();
$smartQueue->updateUserInteraction($user);

// 3. Save incoming message
Message::create([
    'user_id' => $user->id,
    'sender_type' => 'user',
    'content' => $data['text'],
]);

// 4. Generate AI response
$persona = $user->persona;
$chatHistory = Message::where('user_id', $user->id)->latest()->take(20)->get();
$memoryTags = $persona->memoryTags;

$response = $brain->generateChatResponse(
    chatHistory: $chatHistory,
    memoryTags: $memoryTags,
    systemPrompt: $persona->system_prompt
);

// 5. Send response
$telegram->sendStreamingMessage($user->telegram_chat_id, $response);

// 6. Save bot message
Message::create([
    'user_id' => $user->id,
    'sender_type' => 'bot',
    'content' => $response,
]);
```

---

## Next Steps

1. Create webhook controller for handling Telegram updates
2. Create scheduled command for daily plan generation
3. Create job for processing chat responses (queue)
4. Create job for memory extraction (background)
5. Create event scheduler command to execute pending events

---

## Error Handling

All services include comprehensive error logging:
- Failed API calls are logged with context
- Fallback responses are provided for Gemini failures
- Telegram send failures are tracked

Check logs in `storage/logs/laravel.log` for debugging.
