# Current Codebase Overview - December 2025

## 🎯 Project Summary
**AI Virtual Companion** - Laravel 12 application featuring AI-powered virtual companions using Gemini 2.5 Flash with intelligent event scheduling, memory-based conversations, and Telegram bot integration. Supports multimodal generation (text, images, voice).

---

## 🏗️ Architecture Overview

### Core Philosophy: Service Layer Pattern
Business logic lives in **services**, not controllers. All core services are registered as singletons in `AppServiceProvider`.

### Three Pillars

#### 1. **GeminiBrainService** (The AI Brain)
- **Purpose**: All Gemini AI interactions
- **Features**:
  - Chat response generation with memory context
  - Daily plan generation (JSON-based event scheduling)
  - Memory extraction and consolidation
  - Image generation with physical trait consistency
  - Voice note generation integration
  - Function calling for proactive event scheduling
- **Access**: `GeminiBrain::` facade or DI

#### 2. **TelegramService** (The Messenger)
- **Purpose**: Telegram Bot API wrapper
- **Features**:
  - Send/receive messages
  - Photo/document/voice handling
  - Webhook management
  - Chat actions (typing, uploading)
  - File downloads
- **Access**: `Telegram::` facade or DI

#### 3. **SmartQueueService** (The Scheduler)
- **Purpose**: Intelligent event scheduling
- **Features**:
  - "Active conversation" detection (15-min window)
  - Auto-reschedule if user is chatting
  - Event processing with callbacks
  - Timezone-aware scheduling
- **Access**: `SmartQueue::` facade or DI

---

## 📊 Database Schema

### Key Tables

**`personas`**
- One per user
- Stores: `system_prompt`, `physical_traits`, `gender`, `wake_time`, `sleep_time`
- Frequencies: `voice_frequency`, `image_frequency` (never/rare/moderate/frequent)

**`memory_tags`**
- Persona knowledge base
- Fields: `target` (user/self), `category`, `value`, `context`, `importance` (1-10)
- Categories: personality, preferences, physical_look, daily_outfit, night_outfit, current_mood, etc.

**`event_schedules`**
- Scheduled events
- Fields: `type` (text/image_generation), `context_prompt` (JIT instruction), `scheduled_at`, `status` (pending/sent/cancelled)

**`messages`**
- Chat history
- Fields: `sender_type` (user/bot), `content`, `message_splits` (for multi-part messages)

**`users`**
- Extended with: `telegram_chat_id`, `last_interaction_at`

**`media` (Spatie MediaLibrary)**
- Stores all generated media
- Collections: `reference_images`, `generated_images`, `voice_notes`

---

## 🔄 Data Flow

### Chat Message Flow
```
1. Telegram Webhook → TelegramWebhookController
2. Update user.last_interaction_at
3. Dispatch ProcessChatResponse job (with 10s buffer)
4. Load relevant memory tags (tiered: importance → recency → keywords)
5. Build conversation history + memory context
6. Call Gemini with function calling support
7. Extract mood tag [MOOD: value]
8. Process media tags:
   - [GENERATE_IMAGE: ...] → Image generation
   - [SEND_VOICE: ...] → Voice generation
9. Split message by <SPLIT> delimiter
10. Send parts to Telegram with delays (4s between parts)
11. Save to messages table
```

### Image Generation Flow (Recent Fixes)
```
1. AI generates: "SELFIE: Close-up portrait, wearing dress and sandals"
2. Strip outfit from AI description ✅ NEW FIX
3. Get current outfit from memory_tags (daily_outfit/night_outfit)
4. Detect shot type (close-up, full body, selfie, etc.)
5. Filter outfit for shot type:
   - Close-up/Selfie → Remove footwear (no floating shoes) ✅
   - Full body → Keep footwear ✅
6. Filter traits for hijab:
   - Detect "hijab" in outfit
   - Remove ALL hair descriptions from traits ✅ NEW FIX
7. Clean trait sentences (remove "Hana possesses", etc.) ✅
8. Build final prompt:
   - Subject: [Cleaned AI description without outfit]
   - Traits: [Filtered physical traits]
   - Outfit: [Filtered outfit based on shot type]
   - Shot type, lighting, location
9. Send to image generator (KieAI or Cloudflare)
10. Save via MediaLibrary
11. Return public URL
```

### Daily Plan Generation
```
1. Trigger at wake_time
2. Call Gemini with JSON schema
3. Returns: { daily_outfit, night_outfit, events: [...] }
4. Save outfits to memory_tags
5. Create event_schedules for each event
6. Events use JIT (Just-In-Time) generation:
   - Store INSTRUCTION, not final text
   - At scheduled time, generate fresh response with current mood
```

---

## 🎨 Image Generation System

### Multi-Driver Architecture
**ImageGeneratorInterface** with three drivers:

#### KieAiTextToImageDriver (Primary - Default)
- **Model**: `bytedance/seedream-v4-text-to-image`
- **Speed**: ~30 seconds
- **Quality**: High-quality, realistic
- **API**: https://api.kie.ai
- **Method**: Submit task → Poll for completion → Download
- **Use Case**: Generate images from text prompts only

#### KieAiEditDriver (Image-to-Image)
- **Model**: `bytedance/seedream-v4-edit`
- **Speed**: ~30 seconds
- **Quality**: High-quality, consistent with reference images
- **API**: https://api.kie.ai
- **Method**: Submit task with reference images → Poll → Download
- **Use Case**: Generate images based on reference images + text prompt
- **Reference Images**: Up to 10 images from persona's `reference_images` media collection

#### CloudflareFluxDriver (Fallback)
- **Model**: `@cf/black-forest-labs/flux-1-schnell`
- **Speed**: ~5 seconds
- **Quality**: Fast, good
- **API**: Cloudflare Workers AI
- **Method**: Direct sync generation

**Switch in** `.env`:
```env
IMAGE_GENERATOR_DEFAULT=kie_ai  # or cloudflare
```

### Intelligent Trait Filtering (Recent Fixes)

#### Problem 1: Hijab + Hair Conflict ✅ FIXED
**Issue**: Hair descriptions appearing in images when wearing hijab
**Solution**: 
- `filterTraitsForContext()` detects hijab/tudung/headscarf in outfit
- Removes sentences containing hair keywords (hair, ponytail, braid, etc.)
- Uses `removeKeywords()` that filters by **sentence** not individual words
- Preserves eye color, facial features, skin tone

**Example**:
```php
Input: "Dark brown eyes, straight black hair, freckles"
Outfit: "flowy dress with hijab"
Output: "Dark brown eyes, freckles" ✅
```

#### Problem 2: Floating Shoes in Close-Ups ✅ FIXED
**Issue**: Sandals appearing in close-up/selfie shots
**Solution**:
- `filterOutfitForShot()` detects upper-body shots (selfie, close-up, portrait)
- Removes footwear keywords WITH preceding adjectives: "espadrille sandals" → removed entirely
- Cleans up leftover "and ," patterns

**Example**:
```php
Shot: "close-up portrait"
Original: "dress with espadrille sandals, with hijab"
Filtered: "dress, with hijab" ✅
```

#### Problem 3: Outfit Duplication ✅ FIXED
**Issue**: AI description + Our outfit both in prompt
**Solution**:
- Strip "wearing..." clause from AI's description using regex
- Matches full pattern: `wearing X, with Y, and Z`
- Our filtered outfit added separately

**Example**:
```php
AI: "Close-up portrait, wearing dress and sandals, smiling"
After strip: "Close-up portrait, smiling"
Then add: "wearing dress, with hijab" ✅
```

#### Problem 4: Sentence Structure Cleanup ✅ FIXED
**Issue**: "The subject is a woman with A stunning young Korean woman..."
**Solution**:
- Regex removes sentence prefixes: "A stunning young Korean woman with"
- Removes ", Hana possesses" connectors
- Combines sentences: ". Her features include" → ", "

---

## 🧠 Memory System

### Tiered Memory Loading (Performance Optimization)
To prevent context pollution and reduce token usage:

```php
Tier 0: High Importance (importance >= 8) - Always loaded
Tier 1: Recency (updated in last 3 days)
Tier 2: Core Categories (name, age, outfit, mood)
Tier 3: Keyword Relevance (Lite RAG)
  - Food keywords → load food_preference tags
  - Music keywords → load music tags
  - Work keywords → load job/career tags
  - etc.
```

**Benefits**:
- Faster response times
- Reduced Gemini API costs
- More relevant context

### Memory Consolidation
**Job**: `ConsolidateMemories` (runs weekly)
- Deduplicate similar tags
- Prune trivial/outdated facts
- Re-rank importance scores
- Merge redundant entries

---

## 🎭 Persona System

### System Prompt Architecture
Split into two parts:

**1. Identity** (Who the character is)
- Stored in `memory_tags` with target='self'
- Categories: name, age, personality, communication_style, backstory
- Extracted from system_prompt via Gemini

**2. Mechanics** (How the system works)
- Remains in `system_prompt`
- Rules, tools, formatting, anti-repetition

**Migration Tool**: `php artisan app:migrate-bio`
- Extracts identity facts from old monolithic system_prompt
- Stores as memory_tags
- Generates clean mechanics-only template

### Physical Traits Optimization
**Two-step workflow** in PersonaManager:

**Step 1**: Appearance Concept → AI Optimization
```
Input: "cute Korean girl, 22"
Output: "A stunning young Korean woman with oval face, 
         large brown eyes, straight black hair..."
```

**Step 2**: Refine and Save
- Stored in `personas.physical_traits`
- Used in ALL image generation
- Can be overridden by dynamic `physical_look` memory tags

---

## 📱 Telegram Integration

### Webhook Setup
```bash
php artisan telegram:set-webhook  # Set webhook URL
php artisan telegram:remove-webhook  # Remove webhook
```

### Message Processing
- **Buffer System**: Groups messages sent within 10 seconds
- **Split System**: AI uses `<SPLIT>` to separate thoughts
- **Delay System**: 4 seconds between message parts (natural pacing)

### Media Handling
- **Images**: Download → MediaLibrary → Analyze with Gemini Vision
- **Voice**: Download → Transcribe → Process as text
- **Documents**: Download → Store for reference

---

## 🎬 Event Scheduling System

### Just-In-Time (JIT) Generation
Events store **instructions**, not final text:

**Traditional**:
```json
{
  "type": "text",
  "content": "Good morning! Hope you slept well 😊",
  "scheduled_at": "2025-12-04 08:00:00"
}
```

**JIT Approach**:
```json
{
  "type": "text",
  "content": "Send morning greeting. Ask how they slept.",
  "scheduled_at": "2025-12-04 08:00:00"
}
```

**At execution time**:
- Load current mood from memory
- Load recent conversation history
- Generate fresh response based on instruction
- Result feels contextual and adaptive

### Smart Queue Features
- **Active Detection**: Won't send if user chatted <15 min ago
- **Auto-Reschedule**: Postpones event if user is active
- **Timezone Aware**: Respects user timezone settings

---

## 🛠️ Development Workflow

### Quick Commands
```bash
# Setup
composer run setup
php artisan app:create-admin
php artisan storage:link
php artisan app:check

# Development
composer run dev  # Runs: serve, queue, pail, vite

# Testing
php artisan tinker
>>> $persona = Persona::first();
>>> GeminiBrain::generateDailyPlan($persona->memoryTags, ...);

# Telegram
php artisan telegram:set-webhook
php artisan telegram:remove-webhook
```

### Recent Code Quality Improvements

#### Caching Strategy
- **Outfit Cache**: Key = `personaId_hour` (invalidates hourly)
- **Mood Cache**: Key = `personaId_timestamp` (invalidates on update)
- **Performance Gain**: ~79% reduction in repeated queries

#### Helper Methods
Extracted common patterns:
- `hasKeyword(string $text, array $keywords): bool`
- `removeKeywords(string $text, array $keywords): string`
- `cleanupPunctuation(string $text): string`

---

## 🔐 Security & Safety

### NSFW Prevention
`sanitizePromptForImageGeneration()` filters:
- Bedroom/bed → "room"
- Lying/laying → "sitting"
- Lingerie/underwear → "outfit"
- etc.

### Error Handling Philosophy
- **Never throw to UI**: Return friendly Malaysian-style messages
- **Retry Logic**: 3 attempts with exponential backoff (1s, 2s, 4s)
- **Fallback**: Safe default responses on failure
- **Logging**: Comprehensive context in all error logs

---

## 📁 File Structure

```
app/
├── Console/Commands/
│   ├── CheckServices.php
│   ├── CreateAdmin.php
│   ├── GenerateDailyPlan.php
│   └── MigrateBio.php
├── Contracts/
│   └── ImageGeneratorInterface.php
├── Facades/
│   ├── GeminiBrain.php
│   ├── SmartQueue.php
│   └── Telegram.php
├── Jobs/
│   ├── ConsolidateMemories.php
│   ├── ExtractMemoryTags.php
│   ├── ProcessChatResponse.php
│   └── ProcessScheduledEvent.php
├── Livewire/
│   ├── ChatLogs.php
│   ├── Dashboard.php
│   ├── MemoryBrain.php
│   ├── PersonaManager.php ← Trait optimization
│   ├── ScheduleTimeline.php
│   └── TestChat.php
├── Models/
│   ├── EventSchedule.php
│   ├── MemoryTag.php
│   ├── Message.php
│   ├── Persona.php
│   └── User.php
└── Services/
    ├── AudioService.php (ElevenLabs TTS)
    ├── GeminiBrainService.php ← 1782 lines, core logic
    ├── ImageGeneratorManager.php
    ├── ImageGenerators/
    │   ├── CloudflareFluxDriver.php
    │   ├── KieAiTextToImageDriver.php ← Text-to-image (default)
    │   └── KieAiEditDriver.php ← Image-to-image with references
    ├── SmartQueueService.php
    └── TelegramService.php
```

---

## 🎯 Key Features Summary

✅ **AI-Powered Conversations** with Gemini 2.5 Flash
✅ **Memory-Based Context** with tiered loading
✅ **Multimodal Generation** (text, images, voice)
✅ **Intelligent Scheduling** with JIT generation
✅ **Telegram Bot Integration** with webhook support
✅ **Physical Trait Consistency** in images
✅ **Hijab-Aware Hair Filtering** ← Recent fix
✅ **Shot-Type Outfit Filtering** ← Recent fix
✅ **Multi-Driver Image Generation** (KieAI/Cloudflare)
✅ **Admin Dashboard** (Livewire components)
✅ **Memory Consolidation** (auto-cleanup)
✅ **Function Calling** for proactive events

---

## 🚀 Recent Improvements (November 2025)

1. **Image Prompt Generation Overhaul**
   - Fixed hijab + hair conflict
   - Fixed floating shoes in close-ups
   - Fixed outfit duplication
   - Fixed sentence structure cleanup

2. **Performance Optimization**
   - Added caching for outfit/mood
   - Extracted helper methods
   - Smart cache invalidation

3. **Memory System Enhancement**
   - Tiered loading (0-3)
   - Importance-based filtering
   - Keyword-based RAG

4. **Multi-Driver Architecture**
   - Added KieAI driver (primary)
   - Maintained Cloudflare (fallback)
   - Configurable via .env

---

## 📝 Documentation Files

- `README.md` - Project overview
- `SERVICES_README.md` - Complete API reference
- `BRAIN_SERVICE_STRUCTURE.md` - Service organization
- `ARCHITECTURE_REPORT.md` - System design
- `ADMIN_DASHBOARD_GUIDE.md` - Dashboard features
- `QUICK_REFERENCE.md` - Common patterns
- `JIT_QUICK_REFERENCE.md` - Event scheduling
- `.github/copilot-instructions.md` - AI assistant guide

---

**Last Updated**: December 4, 2025
**Framework**: Laravel 12
**PHP**: 8.2+
**Database**: MySQL
**Queue**: Database driver (for development)
