# BrainService Structure

> Last updated: April 2026. Fully migrated from google-gemini-php SDK to Laravel AI SDK.

## Overview

`BrainService` (`app/Services/BrainService.php`) is the AI orchestration hub. It delegates to specialised **AI Agents** (via `laravel/ai`) rather than calling any AI API directly. This means the underlying model and provider can be swapped per-agent without changing business logic.

**Facade:** `Brain::`
**Registered:** Singleton in `AppServiceProvider`

---

## Code Organization

### 1. CONSTANTS
```php
NIGHT_TIME_START = 21  // 9 PM — start of nighttime context
NIGHT_TIME_END   = 6   // 6 AM — end of nighttime context
```

### 2. PUBLIC API METHODS
Main entry points used by controllers, jobs, and Livewire components:

| Method | Agent Used | Description |
|---|---|---|
| `generateChatResponse()` | `PersonaAgent` | Live chat response with full context |
| `generateTestResponse()` | `PersonaAgent` | Chat without DB (TestChat panel) |
| `generate()` | `AnonymousAgent` | One-shot utility prompt |
| `generateEventResponse()` | `EventResponseAgent` | JIT message from event instruction |
| `generateDailyPlan()` | `DailyPlanAgent` | Daily schedule as JSON events array |
| `extractMemoryTags()` | `MemoryExtractionAgent` | Conversation → memory diff |
| `generateImage()` | `ImageGeneratorManager` | Image via configured driver |
| `buildPersonaInstructions()` | — | Full system instructions for PersonaAgent |
| `processMediaTags()` | — | Replace `[GENERATE_IMAGE:]`/`[SEND_VOICE:]` tags |
| `generateImageLoadingMessage()` | — | Short "preparing image" message |
| `setCurrentPersona()` | — | Set persona context for image building |

### 3. MEDIA PROCESSING METHODS (private)
- `processImageTags()` — find `[GENERATE_IMAGE: ...]`, call image generator, replace with `[IMAGE: url]`
- `processVoiceTags()` — find `[SEND_VOICE: ...]`, call `AudioService`, replace with `[AUDIO: url]`
- `buildImagePrompt()` — assemble full image prompt: physical traits + dynamic traits + wardrobe outfit
- `gatherDynamicTraits()` — load `physical_look` memory tags

### 4. IMAGE GENERATION METHODS (private)
- `generateImageForPrompt()` — calls `ImageGeneratorManager::driver()->generate()`
- `saveImageToMediaLibrary()` — store result via Spatie MediaLibrary (`generated_images` collection)
- `filterOutfitForShot()` — trims outfit description for upper-body / close-up shots

### 5. CONTEXT BUILDING METHODS (private)
- `buildMemoryContext(Collection $memoryTags, ?Persona $persona)` — formats memory tags + appends current wardrobe outfit
- `buildConversationHistory()` — formats chat history as `User: ... / Assistant: ...`
- `buildMediaInstructions()` — injects `[GENERATE_IMAGE:]` / `[SEND_VOICE:]` usage rules
- `getRelevantMemoryTags()` — tiered loading (importance → recency → core categories → keyword match)
- `getMoodContext()` — reads `current_mood` memory tag and builds mood context string

### 6. UTILITY METHODS (private)
- `getFallbackDailyPlan()` — returns a safe default schedule if AI fails
- `sanitizePromptForImageGeneration()` — regex NSFW filter with fallback safe prompt

---

## Agent Architecture

Each agent is a class in `app/Ai/Agents/`. They implement `Laravel\Ai\Contracts\Agent` and use the `Promptable` trait. Provider and model are read from `config/ai.php` with fallback to `AI_DEFAULT_MODEL`.

```
config/ai.php
└── agents
	├── chat      → PersonaAgent
	├── planner   → DailyPlanAgent
	├── memory    → MemoryExtractionAgent
	├── event     → EventResponseAgent
	└── utility   → AnonymousAgent (inline)
```

### PersonaAgent
- Implements `Conversational`, `HasTools`
- `instructions()` → calls `Brain::buildPersonaInstructions($persona)` — includes system_prompt, memory, wardrobe, media rules
- `messages()` → prior chat history as `UserMessage`/`AssistantMessage`
- `tools()` → `[new ScheduleEventTool($persona)]`

### DailyPlanAgent
- One-shot (empty `messages()`)
- Instructions: "output structured daily plan as JSON only"
- Full context + JSON schema injected via `prompt()`

### MemoryExtractionAgent
- One-shot (empty `messages()`)
- Instructions: "output structured JSON diff of memory tags"
- Full conversation + existing tags injected via `prompt()`

### EventResponseAgent
- One-shot (empty `messages()`)
- Instructions: "generate a natural warm message from an event instruction"
- Event instruction + memory context injected via `prompt()`

---

## Tool Architecture

### ScheduleEventTool (`app/Ai/Tools/ScheduleEventTool.php`)

Implements `Laravel\Ai\Contracts\Tool`. Available to `PersonaAgent` during live chat.

**Trigger:** AI detects future plan in user message (meeting, appointment, travel)
**Schema:**
- `time` (string, required) — absolute timestamp `YYYY-MM-DD HH:MM`
- `topic` (string, required) — brief description of the event

**Action:** Creates an `EventSchedule` record (`type=text`, `status=pending`) to send a check-in message at the scheduled time.

---

## Memory Context Building

`buildMemoryContext()` uses a 4-tier loading strategy to prevent context bloat:

| Tier | Criteria | Always Loaded |
|---|---|---|
| 0 — High Importance | `importance >= 8` | Yes |
| 1 — Recency | `updated_at >= 3 days ago` | Yes |
| 2 — Core Categories | `basic_info`, `name`, `age`, `location`, `current_mood` | Yes |
| 3 — Keyword Match | Lite RAG: keywords in user message → matched categories | Conditional |

After loading, tags are deduplicated by ID. The wardrobe outfit is appended from `WardrobeService::getTodaysOutfit()` — **not** from memory_tags. Outfit categories (`daily_outfit`, `night_outfit`) were removed from memory in April 2026.

---

## Data Flows

### Chat Response Flow
```
generateChatResponse($chatHistory, $memoryTags, $systemPrompt, $persona)
	↓
getRelevantMemoryTags($persona, $userMessage)   ← tiered loading
	↓
PersonaAgent::make($persona, $history)
	↓ instructions()
	buildPersonaInstructions($persona)
		buildMemoryContext($tags, $persona)     ← memory + wardrobe outfit
		buildMediaInstructions($persona)        ← [GENERATE_IMAGE:] rules
	↓ tools()
	ScheduleEventTool($persona)
	↓ prompt($userMessage)
	AI Response (may contain media tags, <SPLIT>, [MOOD:])
	↓
processMediaTags($response, $persona)
	processImageTags() → ImageGeneratorManager → [IMAGE: url]
	processVoiceTags() → AudioService          → [AUDIO: url]
	↓
Final cleaned response string
```

### Daily Plan Flow
```
generateDailyPlan($memoryTags, $systemPrompt, $wakeTime, $sleepTime)
	↓
buildMemoryContext($memoryTags)    ← no persona = no wardrobe appended
	↓
DailyPlanAgent::make()
	↓ prompt(full context + JSON schema)
	JSON: { "events": [...] }
	↓
json_decode() → validate → return $planData['events']
	↓
Callers (GenerateDailyPlan command / Dashboard / PersonaDashboard)
	save to event_schedules
```

### Image Generation Flow
```
generateImage($description, $persona)
	↓
buildImagePrompt($description, $persona)
	persona->physical_traits          ← permanent traits
	gatherDynamicTraits($persona)     ← memory_tags where category='physical_look'
	Wardrobe::getTodaysOutfit()       ← WardrobeItem->description
	filterOutfitForShot($outfit, $shotType)
	↓
sanitizePromptForImageGeneration($fullPrompt)
	↓
ImageGeneratorManager::driver()->generate($prompt, $persona)
	→ CloudflareFluxDriver | KieAiTextToImageDriver | KieAiEditDriver
	↓
saveImageToMediaLibrary($persona, $imageData)
	→ Spatie MediaLibrary: 'generated_images' collection
	↓
Return public URL
```
