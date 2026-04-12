# Codebase Overview

> Last updated: April 2026

## Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12, PHP 8.3 |
| Frontend | Livewire v3, Volt, Alpine.js v3, Tailwind CSS v3 |
| AI SDK | `laravel/ai` (provider-agnostic OpenAI-compatible gateway) |
| Default AI | LM Studio (local) with `gemma-4-e2b-it-uncensored` |
| Voice | ElevenLabs `eleven_turbo_v2_5` |
| Image (default) | Cloudflare Workers AI — Flux-1-Schnell |
| Image (alt) | Kie.ai — SeDream v4 (text-to-image and edit modes) |
| Media Storage | Spatie MediaLibrary (attached to Persona model) |
| Messaging | Telegram Bot API via `irazasyed/telegram-bot-sdk` |
| Queue | Laravel Queues (database driver) |

---

## Application Structure

```
app/
├── Ai/
│   ├── Agents/          # AI agent classes (PersonaAgent, DailyPlanAgent, ...)
│   └── Tools/           # Agent tools (ScheduleEventTool)
├── Console/Commands/    # Artisan commands (GenerateDailyPlan, ProcessScheduledEvents, ...)
├── Contracts/           # Interfaces (ImageGeneratorInterface)
├── Facades/             # Brain, Telegram, SmartQueue, Wardrobe
├── Http/                # Controllers and Form Requests
├── Jobs/                # ProcessChatResponse, ExtractMemoryTags, ProcessScheduledEvent
├── Livewire/            # Full-page Livewire components (admin dashboard pages)
├── Mcp/                 # MCP server tools
├── Models/              # Eloquent models
├── Providers/           # AppServiceProvider (registers all singletons)
├── Services/            # Business logic (see Service Layer docs)
│   └── ImageGenerators/ # Cloudflare, KieAi drivers
└── View/                # View composers
```

---

## Key Domain Models

| Model | Table | Key Fields |
|---|---|---|
| `User` | `users` | `telegram_chat_id`, `last_interaction_at` |
| `Persona` | `personas` | `user_id`, `system_prompt`, `physical_traits`, `wake_time`, `sleep_time` |
| `MemoryTag` | `memory_tags` | `persona_id`, `target` (user/self), `category`, `value`, `context`, `importance` |
| `Message` | `messages` | `persona_id`, `sender_type` (user/bot), `content` |
| `EventSchedule` | `event_schedules` | `persona_id`, `type` (text/image), `status` (pending/sent/cancelled/rescheduled), `scheduled_at`, `content`, `context_prompt` |
| `WardrobeItem` | `wardrobe_items` | `persona_id`, `slot`, `description`, `is_primary`, style tags |
| `DailyOutfitSelection` | `daily_outfit_selections` | `persona_id`, `wardrobe_item_id`, `slot`, `date` |

---

## Memory Tag Categories

Memory tags store facts about the user or the persona itself. The `target` field distinguishes:
- `'user'` — facts about the human user (name, job, preferences, mood)
- `'self'` — facts about the persona / AI character

### Reserved Core Categories (always loaded into context)
- `basic_info` — general user background
- `name` — user's name
- `age` — user's age
- `location` — user's location
- `current_mood` — current emotional state (updated by AI after each conversation)

> ⚠️ `daily_outfit` and `night_outfit` categories were **removed** in April 2026. Outfits are now managed by `WardrobeService` and `wardrobe_items` table.

### Other Common Categories
- `physical_look` — visual trait overrides for image generation
- `relationship`, `family`, `work`, `hobbies`, `music`, `food`, `health`

---

## Livewire Admin Dashboard Pages

| Component | Route | Purpose |
|---|---|---|
| `Dashboard` | `/dashboard` | Overview, manual daily plan trigger |
| `PersonaManager` | `/persona` | Edit system prompt, physical traits, avatar |
| `MemoryBrain` | `/memory` | CRUD for memory tags |
| `ScheduleTimeline` | `/schedule` | Today's events, cancel pending |
| `ChatLogs` | `/logs` | Message history |
| `TestChat` | `/test-chat` | Live AI chat tester |
| `PersonaGallery` | `/gallery` | Wardrobe and generated image gallery |

All require `auth` + `verified` middleware.

---

## Request Lifecycle (Telegram Message)

```
POST /webhook/telegram
	↓
TelegramWebhookController (validates, extracts chat_id + text)
	↓
SmartQueue::updateUserInteraction($user)
	↓
ProcessChatResponse::dispatch($persona, $message)   [queued]
	↓
Brain::generateChatResponse($history, $tags, $prompt, $persona)
	↓
processMediaTags($response, $persona)
	↓
Telegram::sendMessage / sendPhoto / sendVoice
	↓ (background, every N messages)
ExtractMemoryTags::dispatch($persona)
	↓
Brain::extractMemoryTags($history, $persona) → MemoryTag upsert
```

## Scheduled Commands

| Command | Schedule | Action |
|---|---|---|
| `app:generate-daily-plan` | Daily at persona wake_time | Calls `Brain::generateDailyPlan()`, creates `EventSchedule` records |
| `app:process-scheduled-events` | Every minute | Processes due events via `SmartQueue::processEvent()` |
| `app:select-daily-outfits` | Daily at wake_time | Calls `Wardrobe::getTodaysOutfit()` to pre-select outfits |

---

## External Service Dependencies

Run `php artisan app:check` to verify all connections:

| Service | What It Does | Config Keys |
|---|---|---|
| AI Provider (LM Studio/OpenAI-compatible) | Chat, planning, memory extraction | `AI_DEFAULT_PROVIDER`, `AI_DEFAULT_MODEL` |
| Telegram Bot API | Send/receive messages | `TELEGRAM_BOT_TOKEN` |
| ElevenLabs | Voice synthesis | `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID` |
| Cloudflare Workers AI | Image generation (default) | `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN` |
| Kie.ai | Image generation (alt driver) | `KIE_AI_API_KEY` |
