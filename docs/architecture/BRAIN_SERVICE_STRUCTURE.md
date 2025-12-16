# GeminiBrainService Structure

## Overview
The `GeminiBrainService` is the core AI service that handles all interactions with Gemini AI, Cloudflare image generation, and ElevenLabs voice synthesis.

## Code Organization

### 1. CONSTANTS Section
- `GEMINI_MODEL`: AI model identifier
- `CLOUDFLARE_MODEL`: Image generation model
- `MAX_RETRIES`: Retry attempts for API calls
- `INITIAL_RETRY_DELAY`: Starting delay for exponential backoff
- `IMAGE_NUM_STEPS`: Image generation steps
- `NIGHT_TIME_START/END`: Time boundaries for outfit switching

### 2. PUBLIC API METHODS
Main entry points for external services:
- `generateTestResponse()`: Test chat without database
- `generateChatResponse()`: Full chat with memory
- `generateDailyPlan()`: Morning routine planner
- `extractMemoryTags()`: Memory extraction from conversation
- `generateImage()`: Image generation orchestrator

### 3. MEDIA PROCESSING METHODS
Handle media generation tags:
- `processImageTags()`: Process [GENERATE_IMAGE: ...] tags
- `processVoiceTags()`: Process [SEND_VOICE: ...] tags
- `buildImagePrompt()`: Construct safe image prompts
- `gatherPhysicalTraits()`: Combine all trait sources
- `getCurrentOutfit()`: Get time-appropriate outfit

### 4. CLOUDFLARE API METHODS
Image generation via Cloudflare Workers AI:
- `callCloudflareImageAPI()`: API call wrapper
- `saveGeneratedImage()`: Process and save Base64 image

### 5. GEMINI API METHODS
AI text generation with retry logic:
- `callGeminiWithRetry()`: Exponential backoff for overloaded API

### 6. CONTEXT BUILDING METHODS
Prepare context for AI:
- `buildMemoryContext()`: Format memory tags
- `getCurrentOutfitFromMemory()`: Extract outfit from collection
- `buildConversationHistory()`: Format chat history

### 7. UTILITY METHODS
Helper functions:
- `getFallbackDailyPlan()`: Emergency fallback events
- `sanitizePromptForImageGeneration()`: NSFW content filtering

## Data Flow

### Chat Response Flow
```
User Message
    ↓
generateTestResponse()
    ↓
buildMemoryContext() → buildConversationHistory()
    ↓
callGeminiWithRetry() [with exponential backoff]
    ↓
processImageTags() → generateImage()
    ↓
processVoiceTags() → AudioService
    ↓
Final Response with [IMAGE: url] and [AUDIO: url]
```

### Image Generation Flow
```
Image Description
    ↓
sanitizePromptForImageGeneration()
    ↓
gatherPhysicalTraits()
    ├─ Permanent traits (personas.physical_traits)
    ├─ Dynamic traits (memory_tags.physical_look)
    └─ Current outfit (memory_tags.daily/night_outfit)
    ↓
buildImagePrompt()
    ↓
callCloudflareImageAPI()
    ↓
saveGeneratedImage()
    ↓
Return URL
```

### Daily Plan Flow
```
generateDailyPlan()
    ↓
Gemini generates JSON with:
    - events array
    - daily_outfit
    - night_outfit
    ↓
Dashboard saves:
    - Events to event_schedules table
    - Outfits to memory_tags table
```

## Outfit System

### Time-Based Outfit Selection
- **Daytime (6 AM - 9 PM)**: Uses `daily_outfit`
- **Nighttime (9 PM - 6 AM)**: Uses `night_outfit`

### Storage
- Category: `daily_outfit` or `night_outfit`
- Target: `self`
- Context: Timestamp of when set

### Usage
1. **Chat Context**: Automatically injected via `buildMemoryContext()`
2. **Image Generation**: Automatically included via `gatherPhysicalTraits()`

## Error Handling

### Retry Logic
- Max 3 attempts with exponential backoff (1s, 2s, 4s)
- Handles API overload and rate limits
- Falls back to friendly Malaysian error messages

### NSFW Safety
- Automatic prompt sanitization
- Fallback to safe generic prompt if flagged
- Regex-based word replacement

### Graceful Degradation
- Returns fallback plans on failure
- Replaces failed media with error tags
- Never throws uncaught exceptions to UI

## Configuration

Required environment variables:
```env
GEMINI_API_KEY=your_key
CLOUDFLARE_ACCOUNT_ID=your_account
CLOUDFLARE_API_TOKEN=your_token
ELEVENLABS_API_KEY=your_key
ELEVENLABS_VOICE_ID=your_voice
```

## Future Extensibility

To add new media types:
1. Add processing method in MEDIA PROCESSING section
2. Add tag pattern (e.g., `[NEW_MEDIA: ...]`)
3. Call from `generateTestResponse()`
4. Return replacement tag (e.g., `[MEDIA: url]`)

To add new AI features:
1. Add public method in PUBLIC API section
2. Use existing helpers (context builders, retry logic)
3. Follow consistent error handling patterns
