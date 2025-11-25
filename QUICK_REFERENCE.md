# Quick Reference: Service Layer

## Using Services (Two Ways)

### Method 1: Dependency Injection (Recommended)
```php
use App\Services\GeminiBrainService;
use App\Services\TelegramService;
use App\Services\SmartQueueService;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private GeminiBrainService $brain,
        private TelegramService $telegram,
        private SmartQueueService $smartQueue
    ) {}
    
    public function webhook(Request $request)
    {
        $data = $this->telegram->parseUpdate($request->all());
        // Use services...
    }
}
```

### Method 2: Facades (Quick & Clean)
```php
use App\Facades\GeminiBrain;
use App\Facades\Telegram;
use App\Facades\SmartQueue;

// Send a message
Telegram::sendMessage($chatId, 'Hello!');

// Check if user is active
if (SmartQueue::isUserActive($user)) {
    // User is chatting...
}

// Generate AI response
$response = GeminiBrain::generateChatResponse(
    $chatHistory,
    $memoryTags,
    $systemPrompt
);
```

---

## Common Patterns

### Pattern 1: Handle Incoming Message
```php
use App\Facades\{Telegram, SmartQueue, GeminiBrain};
use App\Models\{User, Message};

public function handleIncomingMessage($update)
{
    $data = Telegram::parseUpdate($update);
    
    // 1. Find or create user
    $user = User::firstOrCreate(
        ['telegram_chat_id' => $data['chat_id']],
        ['name' => $data['user']['first_name']]
    );
    
    // 2. Update interaction timestamp
    SmartQueue::updateUserInteraction($user);
    
    // 3. Save message
    Message::create([
        'user_id' => $user->id,
        'sender_type' => 'user',
        'content' => $data['text'],
    ]);
    
    // 4. Generate response (in a job)
    ProcessChatResponse::dispatch($user);
}
```

### Pattern 2: Generate and Send AI Response (with UX Indicators)
```php
use App\Facades\{GeminiBrain, Telegram};

public function generateResponse($user)
{
    $persona = $user->persona;
    
    // 1. Show "typing..." indicator to user
    Telegram::sendChatAction($user->telegram_chat_id, 'typing');
    
    // 2. Get context
    $chatHistory = Message::where('user_id', $user->id)
        ->latest()
        ->take(20)
        ->get();
    
    $memoryTags = $persona->memoryTags;
    
    // 3. Generate response (this may take a few seconds)
    $response = GeminiBrain::generateChatResponse(
        $chatHistory,
        $memoryTags,
        $persona->system_prompt
    );
    
    // 4. Send to Telegram
    Telegram::sendMessage(
        $user->telegram_chat_id,
        $response
    );
    
    // 5. Save bot message
    Message::create([
        'user_id' => $user->id,
        'sender_type' => 'bot',
        'content' => $response,
    ]);
}
```

### Pattern 3: Process Scheduled Event with Smart Queue
```php
use App\Facades\{SmartQueue, Telegram};
use App\Models\EventSchedule;

public function processEvent($eventId)
{
    $event = EventSchedule::find($eventId);
    
    SmartQueue::processEvent($event, function($event) {
        $user = $event->persona->user;
        
        if ($event->type === 'text') {
            Telegram::sendMessage(
                $user->telegram_chat_id,
                $event->content
            );
        } elseif ($event->type === 'image') {
            Telegram::sendPhoto(
                $user->telegram_chat_id,
                $event->content
            );
        }
    });
}
```

### Pattern 4: Generate Daily Plan
```php
use App\Facades\GeminiBrain;
use App\Models\{Persona, EventSchedule};

public function generateDailyPlan($personaId)
{
    $persona = Persona::find($personaId);
    
    // Generate plan
    $events = GeminiBrain::generateDailyPlan(
        $persona->memoryTags,
        $persona->system_prompt,
        $persona->wake_time,
        $persona->sleep_time
    );
    
    // Save events
    foreach ($events as $eventData) {
        EventSchedule::create([
            'persona_id' => $persona->id,
            'type' => $eventData['type'],
            'content' => $eventData['content'],
            'scheduled_at' => $eventData['scheduled_at'],
            'status' => 'pending',
        ]);
    }
}
```

### Pattern 5: Extract Memory Tags
```php
use App\Facades\GeminiBrain;
use App\Models\{Message, MemoryTag};

public function extractMemories($userId)
{
    $user = User::find($userId);
    $persona = $user->persona;
    
    // Get recent messages
    $recentMessages = Message::where('user_id', $userId)
        ->latest()
        ->take(10)
        ->get();
    
    // Extract memories
    $memoryTags = GeminiBrain::extractMemoryTags(
        $recentMessages,
        $persona->system_prompt
    );
    
    // Save new tags
    foreach ($memoryTags as $tagData) {
        MemoryTag::firstOrCreate(
            [
                'persona_id' => $persona->id,
                'key' => $tagData['key'],
            ],
            [
                'target' => $tagData['target'],
                'value' => $tagData['value'],
            ]
        );
    }
}
```

### Pattern 6: Generate Image with Physical Traits (Consistency)
```php
use App\Facades\{GeminiBrain, Telegram};
use App\Models\EventSchedule;

public function sendScheduledImage($eventId)
{
    $event = EventSchedule::find($eventId);
    $persona = $event->persona;
    $user = $persona->user;
    
    // 1. Show "upload_photo..." indicator
    Telegram::sendChatAction($user->telegram_chat_id, 'upload_photo');
    
    // 2. Generate image with physical traits for consistency
    $imageUrl = GeminiBrain::generateImage(
        $event->content, // The image prompt from event
        $persona->physical_traits // "Short black hair, brown eyes, pale skin"
    );
    
    // 3. Send image to Telegram
    if ($imageUrl) {
        Telegram::sendPhoto(
            $user->telegram_chat_id,
            $imageUrl,
            'Here\'s something for you! 📸'
        );
    }
}
```

---

## Testing in Tinker

```bash
php artisan tinker
```

```php
// Test Telegram
use App\Facades\Telegram;
Telegram::sendMessage('YOUR_CHAT_ID', 'Testing from tinker! 🚀');

// Test chat action indicators
Telegram::sendChatAction('YOUR_CHAT_ID', 'typing');
sleep(2);
Telegram::sendMessage('YOUR_CHAT_ID', 'This message had a typing indicator!');

Telegram::sendChatAction('YOUR_CHAT_ID', 'upload_photo');
sleep(2);
Telegram::sendMessage('YOUR_CHAT_ID', 'This simulated photo upload!');

// Test Smart Queue
use App\Facades\SmartQueue;
use App\Models\User;
$user = User::first();
SmartQueue::updateUserInteraction($user);
SmartQueue::isUserActive($user); // Returns true

// Test Gemini Brain
use App\Facades\GeminiBrain;
use App\Models\Persona;
$persona = Persona::first();
$events = GeminiBrain::generateDailyPlan(
    collect([]),
    'You are a friendly AI companion.',
    '08:00',
    '23:00'
);
print_r($events);
```

---

## Service Aliases

Add to `config/app.php` aliases array for even cleaner usage:

```php
'aliases' => [
    // ... existing aliases
    'GeminiBrain' => App\Facades\GeminiBrain::class,
    'Telegram' => App\Facades\Telegram::class,
    'SmartQueue' => App\Facades\SmartQueue::class,
],
```

---

## Error Handling

All services include built-in error handling:

```php
// Services return false on failure
if (!Telegram::sendMessage($chatId, $message)) {
    Log::error('Failed to send message');
}

// Check logs
tail -f storage/logs/laravel.log
```

---

## Performance Tips

1. **Use Queues**: Always process AI responses in queues
2. **Cache Memory Tags**: Consider caching frequently accessed tags
3. **Limit Chat History**: Keep history to last 20-30 messages
4. **Background Jobs**: Run memory extraction in background
5. **Rate Limiting**: Be mindful of API rate limits (Telegram, Gemini)

---

## Security Checklist

- [ ] Never expose API keys in code
- [ ] Validate webhook requests (Telegram signature)
- [ ] Sanitize user input before sending to AI
- [ ] Use HTTPS for webhooks (production)
- [ ] Implement rate limiting on webhook endpoint
- [ ] Log sensitive operations
- [ ] Set up proper error monitoring

---

## Quick Debug Commands

```bash
# View logs in real-time
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear

# Check queue jobs
php artisan queue:work --verbose

# Run scheduler manually
php artisan schedule:run

# Test webhook endpoint
curl -X POST https://yourdomain.com/api/telegram/webhook \
  -H "Content-Type: application/json" \
  -d '{"message":{"chat":{"id":123},"text":"test"}}'
```

---

That's it! You now have a complete, production-ready service layer for your AI Virtual Companion system. 🎉
