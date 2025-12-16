# Bug Catalog

**Last Updated:** December 16, 2025  
**Epic:** EPIC-002 (Developer Onboarding & Technical Debt Cleanup)  
**Story:** Story 2 (Bug Discovery & Catalog)  
**Analyst:** Mary  

This document catalogs all known bugs in the AI Virtual Companion application, prioritized by severity and impact.

---

## Priority Levels

- **P0 (Critical)** - Breaks core functionality, blocks users completely
- **P1 (High)** - Significant friction, but workarounds exist
- **P2 (Medium)** - Noticeable issues, should be fixed
- **P3 (Low)** - Polish issues, nice-to-fix

---

## P0 - Critical Bugs 🔴

### BUG-001: Queue Worker Timeout on Message Buffer
**Status:** 🔴 Active  
**Severity:** P0 - Critical  
**Impact:** Queue jobs fail, messages may not be processed  
**Reported By:** Nazar  
**Discovered:** December 2025  

**Description:**  
Maximum execution time (180 seconds) exceeded while message buffer is waiting for user reply. This causes the queue worker to crash.

**Evidence:**
```
[2025-12-05 19:30:05] local.ERROR: Maximum execution time of 180 seconds exceeded 
{"exception":"[object] (Symfony\\Component\\ErrorHandler\\Error\\FatalError(code: 0): 
Maximum execution time of 180 seconds exceeded at 
C:\\Users\\nazar\\Herd\\persona\\vendor\\laravel\\framework\\src\\Illuminate\\Queue\\Worker.php:844)
```

**Location:**  
- `vendor/laravel/framework/src/Illuminate/Queue/Worker.php:844`
- Likely in message buffering logic in `TelegramWebhookController` or related jobs

**Reproduction Steps:**
1. Send message to Telegram bot
2. Message gets buffered waiting for user to finish typing
3. Queue worker waits beyond 180 seconds
4. Timeout exception thrown

**Root Cause Hypothesis:**  
The message buffer logic may be polling/waiting synchronously instead of using timeouts or async patterns. The 180-second PHP execution limit is hit before buffer resolves.

**Proposed Fix:**
- Add configurable timeout to message buffer (e.g., 30 seconds max)
- Use queue delay/retry instead of synchronous waiting
- Implement exponential backoff or event-driven pattern

**Tags:** `queue`, `timeout`, `telegram`, `message-buffer`  
**Good First Issue:** ❌ (Requires queue architecture knowledge)

---

### BUG-002: Event Schedule Messages Repeating Non-Stop
**Status:** 🔴 Active  
**Severity:** P0 - Critical  
**Impact:** User receives spam messages (~8 duplicate messages), extremely poor UX  
**Reported By:** Nazar  
**Discovered:** December 2025  

**Description:**  
Scheduled event messages repeat multiple times (approximately 8 messages) saying the same thing in slightly different ways (e.g., "hello" variations). This creates a spam-like experience.

**Location:**  
- `ProcessScheduledEvent` job
- `SmartQueueService` event processing logic
- Event scheduling system

**Reproduction Steps:**
1. Event is scheduled (e.g., daily plan message)
2. Event triggers at scheduled time
3. Same message sent multiple times (~8 times)
4. Each message says essentially the same thing with slight variations

**Root Cause Hypothesis:**
- Event status not being marked as "sent" after first message
- Queue job being dispatched multiple times
- Retry logic incorrectly triggering
- Race condition in event processing

**Proposed Fix:**
1. Add transaction locks to event status updates
2. Implement idempotency key for event processing
3. Mark event as "processing" immediately before sending
4. Add unique constraint or check before sending

**Tags:** `events`, `scheduling`, `duplicate-messages`, `smart-queue`  
**Good First Issue:** ❌ (Requires understanding of event system)

---

### BUG-003: Image Gallery Popup and Pagination Errors
**Status:** 🔴 Active  
**Severity:** P0 - Critical  
**Impact:** Feature completely broken, blocks gallery usage  
**Reported By:** Nazar  
**Discovered:** December 2025  

**Description:**  
The web system's image gallery has errors with the popup modal and pagination/next button functionality. Users cannot properly navigate through images.

**Location:**  
- `PersonaGallery` Livewire component (from STORY-001)
- Gallery modal JavaScript/Alpine.js interactions
- Pagination logic in Blade templates

**Reproduction Steps:**
1. Navigate to persona gallery page (`/personas/{id}/gallery`)
2. Click on an image to open preview modal
3. Error occurs (specific error unknown)
4. Try to paginate/navigate to next image
5. Pagination fails

**Root Cause Hypothesis:**
- JavaScript event listeners not properly attached
- Livewire wire:click conflicts with Alpine.js
- Pagination state not tracked correctly
- Media URLs may be incorrect or expired

**Investigation Needed:**
- Browser console errors
- Network tab inspection
- Livewire error logs

**Proposed Fix:**
- Debug browser console for JavaScript errors
- Verify Livewire component methods exist
- Check Alpine.js event propagation
- Test with different browsers

**Tags:** `livewire`, `gallery`, `frontend`, `modal`, `pagination`  
**Good First Issue:** ✅ (Good for frontend debugging practice)

---

## P1 - High Priority Bugs 🟡

### BUG-004: No User Feedback During Image Generation
**Status:** 🟡 Active  
**Severity:** P1 - High  
**Impact:** User perceives system as frozen, unclear if request is processing  
**Reported By:** Nazar  
**Discovered:** December 2025  

**Description:**  
AI response appears slow/frozen while waiting for image generation from KieAI. User has no indication that image is being generated in the background. This creates confusion and makes users think the system is broken.

**Evidence from Logs:**
```
[2025-12-05 19:48:06] local.INFO: KieAiEditDriver: Generation in progress {"state":"waiting"}
[2025-12-05 19:48:11] local.INFO: KieAiEditDriver: Generation in progress {"state":"waiting"}
[2025-12-05 19:48:17] local.INFO: KieAiEditDriver: Generation in progress {"state":"waiting"}
... (continues for ~28 seconds)
```

**Location:**
- `ProcessChatResponse` job
- Image generation logic in `GeminiBrainService`
- Frontend Telegram message handling

**User Experience Issue:**
- User sends message requesting image
- 20-30 second delay with no feedback
- User doesn't know if request was received
- May send duplicate requests

**Proposed Fix:**
1. Send immediate persona-driven acknowledgment message (e.g., "Wait, just open my camera" or "Give me a sec, fixing my hair")
2. Message should be generated by Gemini using persona context to maintain character
3. Send typing action to Telegram during generation
4. Consider making image generation async with notification when complete

**Example UX Flow:**
```
User: "Send me a selfie"
Bot: "Wait, just open my camera 📸" (Generated by persona AI, maintains character)
[Image generation happens ~20-30 seconds]
Bot: [Sends generated image]
```

**Alternative persona responses:**
- "Give me a sec, fixing my hair first 💁‍♀️"
- "Lemme find good lighting real quick ✨"
- "Hold on, need to adjust my angle 😊"

**Tags:** `ux`, `loading-state`, `image-generation`, `telegram`, `user-feedback`  
**Good First Issue:** ✅ (Simple message addition, good UX improvement)

---

## P2 - Medium Priority Bugs 🟠

### BUG-005: Inconsistent Layout Between Web Pages
**Status:** 🟠 Active  
**Severity:** P2 - Medium  
**Impact:** Inconsistent user experience, looks unprofessional  
**Reported By:** Nazar  
**Discovered:** December 2025  

**Description:**  
Web system pages have inconsistent layouts. Some pages follow different design patterns, spacing, or component styles than others.

**Location:**
- Various Livewire component blade templates
- `resources/views/livewire/*.blade.php`
- Layout files in `resources/views/layouts/`

**Examples of Inconsistency:**
- Navigation placement differs
- Spacing/padding varies
- Button styles not uniform
- Card/container styles differ
- Typography hierarchy inconsistent

**Impact:**
- Looks amateurish
- Confuses users
- Harder to maintain
- Bad impression for potential contributors

**Proposed Fix:**
1. Audit all pages and document layout patterns
2. Create consistent layout component system
3. Define Tailwind component classes in `tailwind.config.js`
4. Refactor pages to use consistent patterns
5. Create style guide documentation

**Investigation Needed:**
- Screenshot comparison of all pages
- Identify which pages are outliers
- Define "standard" layout pattern

**Tags:** `ui`, `layout`, `tailwind`, `consistency`, `design-system`  
**Good First Issue:** ✅ (Good for learning codebase, can tackle page-by-page)

---

## P3 - Low Priority / Historical Issues 🔵

### BUG-006: Livewire Method Not Found (Historical)
**Status:** 🟢 Fixed  
**Severity:** P3 - Low (historical)  
**Impact:** None (already fixed)  
**Evidence Date:** December 5, 2025  

**Description:**  
Livewire method `createPersona` was not found on component. This appears in logs but is likely already fixed during development.

**Evidence:**
```
[2025-12-05 01:37:26] local.ERROR: Unable to call component method. 
Public method [createPersona] not found on component
```

**Status:** Appears resolved (no recent occurrences in logs)

---

### BUG-007: Blade Syntax Error in Dashboard (Historical)
**Status:** 🟢 Fixed  
**Severity:** P3 - Low (historical)  
**Impact:** None (already fixed)  
**Evidence Date:** December 5, 2025  

**Description:**  
Syntax error with unexpected `endforeach` token in `dashboard.blade.php`. Already fixed during development.

**Evidence:**
```
[2025-12-05 01:43:32] local.ERROR: syntax error, unexpected token "endforeach", 
expecting end of file (View: C:\Users\nazar\Herd\persona\resources\views\livewire\dashboard.blade.php)
```

**Status:** Resolved (no recent occurrences)

---

## Bug Summary Statistics

| Priority | Active Bugs | Fixed Bugs | Total |
|----------|-------------|------------|-------|
| P0 (Critical) | 3 | 0 | 3 |
| P1 (High) | 1 | 0 | 1 |
| P2 (Medium) | 1 | 0 | 1 |
| P3 (Low) | 0 | 2 | 2 |
| **Total** | **5** | **2** | **7** |

---

## Good First Issues

These bugs are suitable for new contributors:

1. ✅ **BUG-003** - Image Gallery Popup Errors (Frontend debugging)
2. ✅ **BUG-004** - Add Loading Feedback for Image Generation (Simple UX improvement)
3. ✅ **BUG-005** - Layout Consistency (Can tackle page-by-page)

---

## Priority Recommendations for Story 5 (Quick Win Fixes)

Based on **impact × ease of fix**, recommended order:

1. **BUG-004** (P1) - Add loading feedback → **EASIEST** (~30 minutes)
2. **BUG-003** (P0) - Fix gallery errors → **MEDIUM** (~2 hours)
3. **BUG-002** (P0) - Fix event duplication → **HARD** (~4 hours, needs careful investigation)

**Note:** BUG-001 (timeout) requires deeper architecture review and should be its own story.

---

## Investigation Needed

Some bugs need more information before fixing:

- **BUG-003:** Need specific error messages from browser console
- **BUG-002:** Need to reproduce and capture event processing logs
- **BUG-001:** Need to identify exact blocking code in message buffer

---

## Testing Checklist

Before marking bugs as fixed:

- [ ] Bug cannot be reproduced following original steps
- [ ] Automated test added to prevent regression (if feasible)
- [ ] Manual testing in production-like environment
- [ ] No new bugs introduced by fix
- [ ] Performance impact assessed
- [ ] Documentation updated if behavior changed

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2025-12-16 | Bug catalog created from Nazar interview + log analysis | Mary (Analyst) |

---

## Future Bug Discovery Process

1. **User reports** bug via GitHub issue
2. **Triage** by PM (assign priority P0-P3)
3. **Add to this catalog** with template above
4. **Investigate** reproduction steps
5. **Create story/task** in sprint if P0/P1
6. **Fix** and update status
7. **Verify** and close

---

**Questions or found a new bug?**  
Add it to this file following the template above, or create a GitHub issue.
