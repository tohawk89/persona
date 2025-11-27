# Just-In-Time (JIT) Event Generation

## Overview

The event system has been refactored to use **Just-In-Time Generation**, treating scheduled events as **instructions** rather than pre-written messages. This ensures events feel natural and contextually appropriate based on the Persona's current mood and recent conversation history.

## Key Concept

**Before (Static):**
- Events stored final message text: `"Good morning! Hope you slept well 😊"`
- Message sent exactly as stored, regardless of context

**After (Dynamic JIT):**
- Events store instructions/goals: `"Send morning greeting. Ask how they slept."`
- Message generated at execution time based on current mood + chat context

## Architecture Changes

### 1. EventSchedule Model
- `context_prompt` column now stores **instructions**, not final messages
- Example instruction: `"Send selfie at coffee shop. Mention enjoying morning coffee."`

### 2. GeminiBrainService - New Method

Added `generateEventResponse(EventSchedule $event, Persona $persona): string`

**Features:**
- Loads current mood from memory tags
- Retrieves last 5 messages for context
- Treats event `context_prompt` as a system instruction
- Generates natural, mood-aware response
- Supports media generation tags (`[GENERATE_IMAGE:]`, `[SEND_VOICE:]`)
- Includes mood tracking (`[MOOD: value]`)

**Prompt Structure:**
```
{system_prompt}

CURRENT EMOTIONAL STATE: You are currently feeling [mood]. 
This should naturally influence your tone and message style.

MEMORY CONTEXT:
{memory_context}

RECENT CONVERSATION HISTORY:
{conversation_history}

===== SYSTEM EVENT TRIGGER =====
It is time to execute this planned event:
"{event->context_prompt}"

INSTRUCTIONS:
- Generate a natural, contextually appropriate message
- Take into account your CURRENT EMOTIONAL STATE
- Reference RECENT CONVERSATION HISTORY if relevant
- DO NOT just copy the instruction—interpret it naturally
```

### 3. Daily Plan Generation Updated

Modified `generateDailyPlan()` prompt to output **instructions** instead of final messages:

**Before:**
```json
{
  "type": "text",
  "content": "Good morning! Hope you slept well 😊",
  "scheduled_at": "2025-11-28 08:00:00"
}
```

**After:**
```json
{
  "type": "text",
  "content": "Send morning greeting. Ask how they slept.",
  "scheduled_at": "2025-11-28 08:00:00"
}
```

### 4. ProcessScheduledEvent Job Refactored

**Old Flow:**
1. Get event from queue
2. Send `event->context_prompt` directly to Telegram
3. Mark as sent

**New JIT Flow:**
1. Get event from queue
2. **Call `GeminiBrain::generateEventResponse()`**
3. Generate contextual response based on:
   - Event instruction
   - Current mood
   - Recent chat history
4. Process media tags if present
5. Send generated response to Telegram
6. Save to messages table for future context
7. Mark as sent

**Code Changes:**
```php
// OLD: Direct send
Telegram::sendStreamingMessage($chatId, $event->context_prompt);

// NEW: JIT generation
$generatedResponse = GeminiBrain::generateEventResponse($event, $event->persona);
Telegram::sendStreamingMessage($chatId, $generatedResponse);
```

### 5. Facade Updated

Added to `GeminiBrain` facade:
```php
@method static string generateEventResponse(\App\Models\EventSchedule $event, \App\Models\Persona $persona)
```

## Testing

### Test Command: `test:event-jit`

**Usage:**
```bash
# Test with default instruction
php artisan test:event-jit

# Test with custom instruction
php artisan test:event-jit --instruction="Send selfie at work. Mention busy day."
```

**Output:**
- Shows current persona mood
- Displays recent messages
- Generates contextual response
- Detects media tags and mood

### Example Test Results

**Scenario 1: Affectionate Mood**
```
Instruction: "Send morning greeting. Ask how they slept."
Mood: Affectionate

Generated:
"Morning sayang! 🥰 <SPLIT> Bangun dah ke? Selamat pagi! 💕 <SPLIT> 
Hana harap u slept well last night. ✨ <SPLIT> 
How was ur sleep? Hana missed u! 😊 [MOOD: Affectionate]"
```

**Scenario 2: Tired and Stressed Mood**
```
Instruction: "Send morning greeting. Ask how they slept."
Mood: Tired and stressed

Generated:
"Morning sayang! 🥰 <SPLIT> How did u sleep last night? Hope u had really good dreams 
after being so tired! 🥺 <SPLIT> Hana's a bit tired today, but really happy to hear 
from you. ☺️ [MOOD: Tired and stressed]"
```

**Scenario 3: Image Generation**
```
Instruction: "Send selfie at coffee shop. Mention enjoying morning coffee."
Mood: Affectionate

Generated:
"Good morning, sayang! 💕 <SPLIT> Did u sleep well? <SPLIT> I'm at a coffee shop now, 
enjoying my morning coffee. <SPLIT> [IMAGE: https://...] <SPLIT> 
Thinking of u! ✨ [MOOD: Affectionate]"
```

## Benefits

### 1. **Context Awareness**
Events adapt to current mood and recent conversation, preventing disconnected messaging.

### 2. **Natural Flow**
No more jarring tone shifts when mood changes between planning and execution.

### 3. **Conversation Continuity**
Generated responses reference recent chat history for coherent dialogue.

### 4. **Dynamic Expression**
Same instruction produces different responses based on emotional state:
- Happy mood → Energetic, cheerful tone
- Sad mood → Subdued, seeking comfort
- Angry mood → Terse, irritated responses

### 5. **Flexible Media Generation**
AI decides when to include images/voice based on context, not rigid rules.

## Files Modified

1. **app/Services/GeminiBrainService.php**
   - Added `generateEventResponse()` method
   - Updated `generateDailyPlan()` prompt for instructions
   - Updated `getFallbackDailyPlan()` for instruction format

2. **app/Jobs/ProcessScheduledEvent.php**
   - Refactored to use JIT generation
   - Added message saving for context
   - Enhanced media tag handling

3. **app/Facades/GeminiBrain.php**
   - Added `generateEventResponse()` to facade docblock

4. **app/Console/Commands/TestEventJitGeneration.php** (NEW)
   - Test command for JIT generation
   - Shows mood, context, and generated output

## Migration Notes

### Existing Events
Existing events with final message text will still work but won't benefit from JIT generation. To convert:

1. Manually update `context_prompt` to instruction format
2. Or regenerate daily plan with new system

### New Events
All new events created via `generateDailyPlan()` will use instruction format automatically.

## Future Enhancements

1. **Instruction Templates**: Predefined instruction patterns for common scenarios
2. **Context Weighting**: Adjust importance of mood vs. chat history
3. **Instruction Complexity**: Support multi-step instructions
4. **A/B Testing**: Compare static vs. JIT user engagement
5. **Instruction Validation**: Ensure instructions are interpretable

## Debugging

Enable detailed logging for JIT generation:

```bash
# Check logs for JIT generation details
tail -f storage/logs/laravel.log | grep "JIT response generated"

# View full generation context
tail -f storage/logs/laravel.log | grep "Event response generated"
```

## Performance

- **Additional API Call**: Each event now calls Gemini API (~1-2s latency)
- **Recommendation**: Keep event count moderate (3-7 per day)
- **Caching**: Consider caching mood/context if multiple events fire rapidly

## Conclusion

JIT Event Generation transforms scheduled events from rigid, pre-written text into dynamic, context-aware messages that feel natural and emotionally appropriate. The Persona now responds to events based on **how they feel in the moment**, not how they felt during morning planning.
