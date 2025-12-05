# Multi-Persona Setup Guide

## ⚠️ CRITICAL: Preventing Message Cross-Contamination

When using **multiple personas for the same user/chat_id**, you **MUST** configure them correctly to avoid cross-contamination where one persona responds to messages meant for another.

## Correct Setup Patterns

### Pattern 1: One Persona per User (Recommended)
```
User: Nazar (chat_id: 527908393)
└── Persona: Hana
    ├── is_active: true
    ├── telegram_bot_token: 8474976795:AAHf... (dedicated bot)
    └── Webhook: /telegram/webhook/8474976795:AAHf...
```

**Result**: All messages to Hana's bot → Hana responds ✅

---

### Pattern 2: Multiple Personas with Dedicated Bots (Advanced)
```
User: Nazar (chat_id: 527908393)
├── Persona: Hana
│   ├── is_active: true
│   ├── telegram_bot_token: 8474976795:AAHf... (dedicated bot #1)
│   └── Webhook: /telegram/webhook/8474976795:AAHf...
│
└── Persona: Fariza
    ├── is_active: true
    ├── telegram_bot_token: 7919951108:AAE-... (dedicated bot #2)
    └── Webhook: /telegram/webhook/7919951108:AAE-...
```

**Result**: 
- Messages to Hana's bot → Hana responds ✅
- Messages to Fariza's bot → Fariza responds ✅
- Each bot maintains separate conversation context ✅

**IMPORTANT**: You need to message **different Telegram bots** (not the same chat). Each persona has its own bot username (e.g., @HanaBot, @FarizaBot).

---

### Pattern 3: Multiple Personas with System Default Bot (NOT RECOMMENDED)
```
User: Nazar (chat_id: 527908393)
├── Persona: Hana
│   ├── is_active: true
│   └── telegram_bot_token: null (uses system bot)
│
└── Persona: Fariza
    ├── is_active: true
    └── telegram_bot_token: null (uses system bot)
```

**⚠️ PROBLEM**: 
- Both personas use the same system bot
- System picks the first active persona or one without dedicated token
- **Cross-contamination risk**: Messages meant for Hana might go to Fariza!

**Solution**: 
1. Set `is_active: false` for personas you're not currently using
2. OR assign dedicated bot tokens to each persona

---

## Current Issue Resolution

### Your Current Setup:
```
User: Nazar (chat_id: 527908393)
├── Persona: Hana (ID: 1)
│   ├── is_active: YES ⚠️
│   ├── telegram_bot_token: 8474976795:AAHf...
│   └── Messages: 251
│
└── Persona: Fariza (ID: 3)
    ├── is_active: YES ⚠️
    ├── telegram_bot_token: 7919951108:AAE-...
    └── Messages: 14
```

### Why Cross-Contamination Might Occur:
1. **Both are active** - If system default bot is used, it might pick the wrong one
2. **Shared chat_id** - Both personas respond to the same Telegram account

### Solutions:

#### Option A: Use Separate Bot Chats (Recommended)
Each persona should be messaged via its **own bot**:
- Talk to **@HanaBot** (token: 8474976795...) for Hana
- Talk to **@FarizaBot** (token: 7919951108...) for Fariza

These are **separate Telegram conversations** - no cross-contamination possible!

#### Option B: Deactivate Unused Personas
If you only want to use Hana:
```sql
UPDATE personas SET is_active = 0 WHERE id = 3; -- Deactivate Fariza
```

#### Option C: Use Context Switching Command (Future Feature)
Implement a `/switch` command to toggle between active personas in the same chat.

---

## Verification Command

Run this to check your current setup:
```bash
php check_message_logs.php
```

Look for warnings about:
- Multiple active personas sharing the same chat_id
- Missing or misconfigured bot tokens

---

## Technical Implementation Notes

### Webhook Routing Logic:
1. Incoming message → `/telegram/webhook/{token}`
2. System checks: `Persona::where('telegram_bot_token', $token)->first()`
3. If found → Use that specific persona ✅
4. If not found → Check if `$token` is system default
5. If system default → Find user's active persona (picks first or one without dedicated token)

### Message Storage:
- Each message is tagged with `persona_id`
- Chat history is filtered by `persona_id`
- Buffers use key: `chat_buffer_{chat_id}_{persona_id}`

This ensures **conversation context is isolated per persona** even if they share the same user/chat_id.
