# Service Layer Summary

> Last updated: April 2026

## What Exists

### AI Layer: Agents (`app/Ai/Agents/`)

| Agent | Config Key | Purpose |
|---|---|---|
| `PersonaAgent` | `ai.agents.chat` | Live chat — conversational, has tools, full persona context |
| `DailyPlanAgent` | `ai.agents.planner` | One-shot JSON daily schedule generation |
| `MemoryExtractionAgent` | `ai.agents.memory` | One-shot memory diff extraction from conversation |
| `EventResponseAgent` | `ai.agents.event` | One-shot JIT message from event instruction |

All agents use the `Promptable` trait from `laravel/ai`. Provider and model are independently configurable per agent via `.env` (e.g. `AI_CHAT_MODEL`, `AI_PLANNER_MODEL`). All fall back to `AI_DEFAULT_MODEL`.

### AI Layer: Tools (`app/Ai/Tools/`)

| Tool | Used By | Trigger | Action |
|---|---|---|---|
| `ScheduleEventTool` | `PersonaAgent` | AI detects future plan in user message | Creates `EventSchedule` record for later delivery |

### Core Services (`app/Services/`)

| Service | Facade | Key Responsibility |
|---|---|---|
| `BrainService` | `Brain::` | AI orchestration: delegates to agents, processes media tags, builds context |
| `TelegramService` | `Telegram::` | Telegram Bot API: send text/photo/voice, webhook management |
| `SmartQueueService` | `SmartQueue::` | Smart event delivery: skip/reschedule if user is actively chatting |
| `WardrobeService` | `Wardrobe::` | Outfit selection (70/30 rotation), AI generation, daily caching |
| `AudioService` | — | ElevenLabs TTS → MP3 → MediaLibrary or Storage |
| `ImageGeneratorManager` | — | Driver manager: Cloudflare Flux / Kie.ai text-to-image / Kie.ai edit |

### Image Generator Drivers (`app/Services/ImageGenerators/`)

| Driver | Model | Notes |
|---|---|---|
| `CloudflareFluxDriver` | `@cf/black-forest-labs/flux-1-schnell` | Default, 4-step, NSFW fallback |
| `KieAiTextToImageDriver` | `bytedance/seedream-v4-text-to-image` | Async polling, 2 min max |
| `KieAiEditDriver` | `bytedance/seedream-v4-edit` | Uses persona reference images |

---

## What Was Removed / Renamed

| Old | New | Reason |
|---|---|---|
| `GeminiBrainService` | `BrainService` | Model-agnostic rename |
| `GeminiBrain::` facade | `Brain::` facade | Model-agnostic rename |
| `callGemini()` method | `generate()` method | Model-agnostic rename |
| google-gemini-php SDK | laravel/ai SDK | Full provider abstraction |
| `daily_outfit` / `night_outfit` memory tags | `WardrobeService` + `wardrobe_items` table | Structured wardrobe system |
| Gemini JSON mode (`ResponseMimeType::JSON`) | Prompt-level JSON instruction + `json_decode()` | Works across all providers |

---

## Configuration Files

**`config/ai.php`** — AI provider and per-agent model config
```env
AI_DEFAULT_PROVIDER=lmstudio
AI_DEFAULT_MODEL=gemma-4-e2b-it-uncensored
AI_CHAT_PROVIDER=       # override for PersonaAgent
AI_PLANNER_PROVIDER=    # override for DailyPlanAgent
AI_MEMORY_PROVIDER=     # override for MemoryExtractionAgent
AI_EVENT_PROVIDER=      # override for EventResponseAgent
AI_UTILITY_PROVIDER=    # override for anonymous generate()
```

**`config/services.php`** — External service keys
```env
TELEGRAM_BOT_TOKEN=
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_ID=
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_API_TOKEN=
KIE_AI_API_KEY=
IMAGE_GENERATOR_DRIVER=cloudflare
```

---

## Facade Summary

```php
// Registered singletons accessible as facades:
Brain::generateChatResponse($history, $tags, $prompt, $persona);
Brain::generateDailyPlan($tags, $prompt, $wake, $sleep);
Brain::extractMemoryTags($history, $persona);
Brain::generateImage($description, $persona);
Brain::processMediaTags($text, $persona);
Brain::generate($prompt);

Telegram::sendMessage($chatId, $text);
Telegram::sendPhoto($chatId, $url, $caption);
Telegram::sendVoice($chatId, $audioUrl);
Telegram::parseUpdate($webhookPayload);

SmartQueue::processEvent($event, fn($e) => ...);
SmartQueue::isUserActive($user);
SmartQueue::updateUserInteraction($user);

Wardrobe::getTodaysOutfit($persona, 'daytime');
Wardrobe::buildOutfitDescription($wardrobeItem, 'full_body');
Wardrobe::generateOutfits($persona, 'casual_daytime', ...);
```
