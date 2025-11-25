# AI Virtual Companion - Copilot Instructions

## Project Overview
Laravel 12 application for AI-powered virtual companions using Gemini 2.5 Flash. Features intelligent event scheduling, memory-based conversations, and Telegram bot integration with image/voice generation.

## Architecture

### Service Layer Pattern
Business logic lives in **services**, not controllers. Three core services registered as singletons in `AppServiceProvider`:

1. **GeminiBrainService** - Gemini AI integration (chat, daily plans, memory extraction, image generation via Cloudflare Workers AI)
2. **TelegramService** - Telegram Bot API wrapper (messages, webhooks, file handling)
3. **SmartQueueService** - Event scheduling with "active conversation" detection (reschedules if user chatted within 15 min)

Access via facades: `GeminiBrain::`, `Telegram::`, `SmartQueue::` or dependency injection.

### Key Code Organization
- Services use **section comments** for structure (see `GeminiBrainService.php`):
  - `CONSTANTS` → `PUBLIC API METHODS` → `MEDIA PROCESSING` → `API METHODS` → `CONTEXT BUILDING` → `UTILITY`
- Code should be organized top-to-bottom by abstraction level
- Keep related functionality grouped under clear section markers

### Data Flow
```
Telegram Webhook → Update user.last_interaction_at → Queue job → 
Generate AI response with memory context → Process [GENERATE_IMAGE:] / [SEND_VOICE:] tags → 
Send to Telegram → Save to messages table
```

## Database Schema Conventions

### Key Tables
- `personas` - One per user, stores `system_prompt`, `physical_traits`, `wake_time`, `sleep_time`
- `memory_tags` - Facts about user/persona with `target` ('user'|'self'), `category`, `value`, `context`
- `event_schedules` - Scheduled messages with `type` ('text'|'image'), `status` ('pending'|'sent'|'cancelled'), `scheduled_at`
- `messages` - Chat history with `sender_type` ('user'|'bot')
- `users` - Extended with `telegram_chat_id`, `last_interaction_at`

### Migration Pattern
- Use anonymous class migrations: `return new class extends Migration`
- Foreign keys always cascade on delete: `->constrained()->onDelete('cascade')`
- Enums for fixed value sets: `->enum('target', ['user', 'self'])`

## Development Workflows

### Initial Setup
```bash
composer run setup  # Installs deps, generates key, migrates, builds frontend
php artisan app:create-admin  # Create admin user for dashboard
php artisan storage:link  # Link public storage for avatars
```

### Development Server
```bash
composer run dev  # Runs: serve, queue:listen, pail (logs), and vite in parallel
```

### Testing Services (Tinker)
```php
use App\Facades\{GeminiBrain, Telegram, SmartQueue};
$persona = Persona::first();
$events = GeminiBrain::generateDailyPlan($persona->memoryTags, $persona->system_prompt, '08:00', '23:00');
Telegram::sendMessage($chatId, 'Test message');
```

## Critical Patterns

### Media Tag Processing
AI responses can contain tags that trigger media generation:
- `[GENERATE_IMAGE: description]` → Cloudflare Flux-1-Schnell → `[IMAGE: url]`
- `[SEND_VOICE: text]` → ElevenLabs TTS → `[AUDIO: url]`

Process in services, not views. See `processImageTags()` and `processVoiceTags()` in `GeminiBrainService`.

### Image Generation Consistency
Always pass `physical_traits` + dynamic outfit from memory tags:
```php
$traits = $persona->physical_traits; // "Short black hair, brown eyes"
$outfit = getCurrentOutfit(); // From memory_tags where category='daily_outfit'
$imageUrl = GeminiBrain::generateImage($description, $persona);
```

### Smart Queue Logic
Events auto-reschedule if user is active (15 min window):
```php
SmartQueue::processEvent($event, function($event) {
    // Execute only if user inactive
    Telegram::sendMessage($chatId, $event->content);
});
```

### Time-Based Outfit System
- **Daytime** (6 AM - 9 PM): Uses `daily_outfit` from memory_tags
- **Nighttime** (9 PM - 6 AM): Uses `night_outfit` from memory_tags
- Automatically injected into AI context and image generation

### Error Handling Philosophy
- Never throw exceptions to UI - return friendly Malaysian-style messages: `"Adoi, ada masalah sikit..."`
- Log errors with context: `Log::error('Service: Action failed', ['context' => $data])`
- Use exponential backoff for API retries (3 attempts, 1s→2s→4s delays)
- Fallback gracefully (e.g., `getFallbackDailyPlan()` returns safe default events)

## Livewire Components

### Admin Dashboard Pages (Full-Page Components)
All require `auth` + `verified` middleware:
- `Dashboard` - Status overview, manual wake-up trigger
- `PersonaManager` - Edit system prompt, physical traits, wake/sleep times, avatar upload
- `MemoryBrain` - CRUD for memory tags with modal forms
- `ScheduleTimeline` - Today's events, cancel pending events
- `ChatLogs` - Message history display
- `TestChat` - Live AI chat tester with event simulation

### Livewire Conventions
- Full-page components use `->layout('layouts.app')`
- Use `WithFileUploads` trait for file handling (avatars stored in `public/avatars/`)
- Dispatch browser events for scroll: `$this->dispatch('chat-message-sent')`

## Environment Configuration

### Required API Keys
```env
GEMINI_API_KEY=           # Google Gemini 2.5 Flash
TELEGRAM_BOT_TOKEN=       # Telegram Bot API
TELEGRAM_WEBHOOK_URL=     # HTTPS endpoint (production only)
CLOUDFLARE_ACCOUNT_ID=    # For image generation
CLOUDFLARE_API_TOKEN=
ELEVENLABS_API_KEY=       # For voice synthesis
ELEVENLABS_VOICE_ID=
```

### Service Configuration
All API credentials in `config/services.php` under respective service keys.

## AI Generation Specifics

### System Prompt Usage
Stored in `personas.system_prompt`. Injected into every AI call with memory context. Example structure:
```
You are [persona name]. Your personality: [traits].
Communication style: [style].
Special instructions: [behaviors].
```

### Memory Context Building
See `buildMemoryContext()` - formats memory_tags into readable context:
```
USER FACTS:
- music: likes Linkin Park
- work: Software engineer

SELF FACTS:
- daily_outfit: Blue dress with white sneakers
```

### Daily Plan Generation
Returns structured JSON with 5 events + outfit assignments. See `generateDailyPlan()` - uses Gemini with `ResponseMimeType::JSON`.

### NSFW Safety
All image prompts pass through `sanitizePromptForImageGeneration()` - regex-based filtering + fallback to generic safe prompt if flagged.

## Missing Implementation (TODO)
Controllers, jobs, and scheduled commands still need creation:
- `TelegramWebhookController` - Handle incoming Telegram updates
- Jobs: `ProcessChatResponse`, `ExtractMemoryTags`, `ProcessScheduledEvent`
- Commands: `GenerateDailyPlan`, `ProcessScheduledEvents`

See `SERVICE_LAYER_SUMMARY.md` for detailed implementation structure.

## Documentation References
- `SERVICES_README.md` - Complete API reference with examples
- `BRAIN_SERVICE_STRUCTURE.md` - Code organization and data flows
- `ADMIN_DASHBOARD_GUIDE.md` - Dashboard feature details
- `QUICK_REFERENCE.md` - Common usage patterns and testing

## Code Style
- Follow Laravel conventions (PSR-12, StudlyCase for classes, camelCase for methods)
- Use type hints everywhere: `function method(Type $param): ReturnType`
- Prefer explicit over implicit - no magic unless framework standard
- Group imports by: Laravel framework → Third-party → App namespace
