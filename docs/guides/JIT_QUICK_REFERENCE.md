# JIT Event Generation - Quick Reference

## Testing JIT Generation

```bash
# Basic test with default instruction
php artisan test:event-jit

# Test with custom instruction
php artisan test:event-jit --instruction="Send selfie at gym. Mention workout feels great."

# Test image generation instruction
php artisan test:event-jit --instruction="Share photo of lunch. Ask what they're eating."
```

## Writing Good Event Instructions

### ✅ Good Instructions (Goals/Directions)
```
"Send morning greeting. Ask how they slept."
"Share selfie at coffee shop. Mention enjoying the morning."
"Check in on their work day. Show interest in how it's going."
"Send goodnight message. Express that you miss them."
"Share photo from gym. Mention feeling energized after workout."
```

### ❌ Bad Instructions (Pre-written Messages)
```
"Good morning! Hope you slept well 😊"  ← Too specific, no room for context
"Here's a selfie at the coffee shop! ☕"  ← Already written, not an instruction
"How is work going today?"  ← Direct question, not a goal
```

## How Instructions Are Interpreted

**Instruction:** `"Send morning greeting. Ask how they slept."`

**Context:**
- Mood: Affectionate
- Last message: "Goodnight sayang 💕"

**Generated:**
```
Morning sayang! 🥰
<SPLIT>
Did u sleep well last night?
<SPLIT>
Hana missed u! 💕
[MOOD: Affectionate]
```

---

**Same Instruction, Different Mood:**

**Context:**
- Mood: Tired and stressed
- Last message: "Goodnight sayang 💕"

**Generated:**
```
Morning...
<SPLIT>
How did u sleep?
<SPLIT>
Hana's so tired today 😮‍💨
[MOOD: Tired and stressed]
```

## Event Types

### Text Events
```json
{
  "type": "text",
  "content": "Send afternoon check-in. Ask about their lunch.",
  "scheduled_at": "2025-11-28 13:00:00"
}
```

### Image Generation Events
```json
{
  "type": "image_generation",
  "content": "Send selfie at park. Mention enjoying the sunshine.",
  "scheduled_at": "2025-11-28 15:00:00"
}
```

## Changing Mood for Testing

```bash
# Set to Happy
php artisan tinker --execute="App\Models\MemoryTag::where('category', 'current_mood')->first()->update(['value' => 'Happy and energetic']);"

# Set to Sad
php artisan tinker --execute="App\Models\MemoryTag::where('category', 'current_mood')->first()->update(['value' => 'Sad and lonely']);"

# Set to Tired
php artisan tinker --execute="App\Models\MemoryTag::where('category', 'current_mood')->first()->update(['value' => 'Tired and stressed']);"

# Reset to Affectionate
php artisan tinker --execute="App\Models\MemoryTag::where('category', 'current_mood')->first()->update(['value' => 'Affectionate']);"
```

## Manual Event Processing

```bash
# Process all due events now
php artisan app:process-scheduled-events

# Generate new daily plan
php artisan app:generate-daily-plan
```

## Debugging JIT Generation

```bash
# Watch JIT generation in real-time
tail -f storage/logs/laravel.log | grep "JIT response generated"

# See full event context
tail -f storage/logs/laravel.log | grep "Event response generated"

# Check event execution
tail -f storage/logs/laravel.log | grep "ProcessScheduledEvent"
```

## Example Daily Plan (New Format)

```json
{
  "daily_outfit": "white sundress with sandals",
  "night_outfit": "silk pajamas",
  "events": [
    {
      "type": "text",
      "content": "Send morning greeting. Ask how they slept.",
      "scheduled_at": "2025-11-28 08:00:00"
    },
    {
      "type": "image_generation",
      "content": "Send selfie at coffee shop. Mention morning coffee.",
      "scheduled_at": "2025-11-28 10:30:00"
    },
    {
      "type": "text",
      "content": "Check in during lunch time. Ask what they're eating.",
      "scheduled_at": "2025-11-28 12:30:00"
    },
    {
      "type": "image_generation",
      "content": "Share sunset photo from park. Express peaceful feeling.",
      "scheduled_at": "2025-11-28 18:00:00"
    },
    {
      "type": "text",
      "content": "Send goodnight message. Express missing them.",
      "scheduled_at": "2025-11-28 22:00:00"
    }
  ]
}
```

## Common Use Cases

### Morning Routine
```
Instruction: "Send morning greeting. Ask how they slept."
→ Adapts based on if they were tired last night, having trouble sleeping, etc.
```

### Check-In Messages
```
Instruction: "Check in on their work/study. Show interest."
→ References recent conversation about their job/studies
→ Tone matches current mood (supportive if stressed, cheerful if happy)
```

### Selfie/Photo Events
```
Instruction: "Send selfie at location. Mention current activity."
→ AI generates contextual caption
→ Includes [GENERATE_IMAGE:] tag with description
→ Caption tone matches mood
```

### Goodnight Messages
```
Instruction: "Send goodnight message. Express feelings."
→ If had argument: Softer, apologetic tone
→ If had great day: Warm, affectionate tone
→ If they're traveling: "Sleep well, can't wait to hear about your trip"
```

## Tips for Best Results

1. **Be Descriptive**: Include what action to take and what to mention
2. **Goal-Oriented**: Focus on the intent, not exact wording
3. **Context Clues**: Reference situations that might be relevant
4. **Flexible**: Let AI interpret based on mood and conversation
5. **Natural Timing**: Schedule events at realistic times

## Architecture Flow

```
1. Event scheduled with instruction
   └─ "Send morning greeting. Ask how they slept."

2. Event due time arrives
   └─ SmartQueue checks if user active

3. Generate JIT response
   ├─ Load current mood: "Affectionate"
   ├─ Load recent messages (last 5)
   ├─ Call GeminiBrain::generateEventResponse()
   └─ AI interprets instruction with context

4. Process generated response
   ├─ Handle [GENERATE_IMAGE:] tags
   ├─ Handle [SEND_VOICE:] tags
   └─ Extract [MOOD:] for memory update

5. Send to Telegram
   └─ User receives natural, contextual message

6. Save to messages table
   └─ Becomes part of future context
```
