# Good First Issues

Welcome, new contributors! 👋

This document lists beginner-friendly tasks perfect for your first contribution to AI Virtual Companion. Each issue is tagged with difficulty, time estimate, and required skills.

---

## How to Pick an Issue

1. **Read the issue description** carefully
2. **Check required skills** - Pick one that matches your strengths
3. **Estimate time commitment** - Can you dedicate this time?
4. **Comment on the issue** to claim it (prevents duplicate work)
5. **Ask questions** if anything is unclear

---

## Available Issues

### 🟢 ISSUE-001: Add Loading Feedback for Image Generation
**Reference:** BUG-004 in [BUGS.md](../BUGS.md)

**Description:**  
Currently, when users request an image, there's a 20-30 second delay with no feedback. Add a persona-driven loading message (e.g., "Wait, just open my camera 📸") that maintains the AI companion character.

**Difficulty:** 🟢 Easy  
**Time Estimate:** 30 minutes  
**Skills Required:**
- Basic PHP
- Understanding of Gemini AI prompts
- Familiarity with Laravel services

**Files to Modify:**
- `app/Services/GeminiBrainService.php` (processImageTags method)
- Possibly `app/Jobs/ProcessChatResponse.php`

**Acceptance Criteria:**
- [ ] Before image generation starts, call Gemini with prompt: "User asked for image. What do you say while preparing it? (1 sentence, in character)"
- [ ] Send that response to Telegram immediately
- [ ] Then proceed with image generation
- [ ] Test with live Telegram bot
- [ ] Update comments/docblocks

**Resources:**
- [BUGS.md](../BUGS.md#bug-004-no-user-feedback-during-image-generation)
- [docs/architecture/SERVICES_README.md](../docs/architecture/SERVICES_README.md)

---

### 🟢 ISSUE-002: Extract Timeout Constants to Configuration
**Reference:** Quick Win #6 in [TECHNICAL_DEBT.md](../TECHNICAL_DEBT.md)

**Description:**  
Hard-coded timeout values (15 min, 30 min, 180s) are scattered throughout the codebase. Extract them to `config/services.php` for easier maintenance and environment-specific tuning.

**Difficulty:** 🟢 Easy  
**Time Estimate:** 15 minutes  
**Skills Required:**
- Basic PHP
- Laravel configuration system
- Search/replace skills

**Files to Modify:**
- `config/services.php` (add timeout section)
- `app/Services/SmartQueueService.php` (replace hard-coded 15, 30)
- `app/Jobs/ProcessChatResponse.php` (replace timeout values)
- Any other files with magic numbers

**Acceptance Criteria:**
- [ ] Add `timeouts` array to `config/services.php` with keys: `active_conversation_window`, `event_reschedule_delay`, `queue_worker_timeout`
- [ ] Replace all hard-coded values with `config('services.timeouts.key')`
- [ ] Verify no functionality breaks
- [ ] Add comments explaining what each timeout controls

**Resources:**
- [Laravel Configuration Docs](https://laravel.com/docs/configuration)
- [TECHNICAL_DEBT.md](../TECHNICAL_DEBT.md#19-quick-wins-story-5-candidates)

---

### 🟡 ISSUE-003: Fix PersonaGallery Pagination Errors
**Reference:** BUG-003 in [BUGS.md](../BUGS.md)

**Description:**  
The PersonaGallery page has errors with the preview modal and pagination/next button. Users cannot navigate through images properly. This requires frontend debugging skills.

**Difficulty:** 🟡 Medium  
**Time Estimate:** 2 hours  
**Skills Required:**
- Livewire 3
- Alpine.js
- Browser DevTools (Console, Network tab)
- Basic debugging

**Files to Modify:**
- `app/Livewire/PersonaGallery.php`
- `resources/views/livewire/persona-gallery.blade.php`
- Possibly routes in `routes/web.php`

**Acceptance Criteria:**
- [ ] Gallery modal opens without errors
- [ ] Pagination buttons work correctly
- [ ] Console has zero JavaScript errors
- [ ] Test with multiple pages of media
- [ ] Add test case to `tests/Feature/PersonaGalleryTest.php`

**Debugging Steps:**
1. Open browser DevTools Console
2. Navigate to `/personas/{id}/gallery`
3. Click image → Capture console errors
4. Click Next → Capture network errors
5. Identify root cause (likely Livewire wire:click or route issue)

**Resources:**
- [Livewire Debugging](https://livewire.laravel.com/docs/troubleshooting)
- [BUGS.md](../BUGS.md#bug-003-image-gallery-popup-and-pagination-errors)

---

### 🟡 ISSUE-004: Add Basic Service Layer Unit Test
**Reference:** Test Strategy in [TECHNICAL_DEBT.md](../TECHNICAL_DEBT.md)

**Description:**  
The codebase has ~2% test coverage with zero service layer tests. Write the first unit test for `GeminiBrainService::sanitizePromptForImageGeneration()` to establish testing patterns.

**Difficulty:** 🟡 Medium  
**Time Estimate:** 1 hour  
**Skills Required:**
- PHPUnit
- Laravel testing
- Understanding of mocking

**Files to Create:**
- `tests/Unit/Services/GeminiBrainServiceTest.php`

**Acceptance Criteria:**
- [ ] Create test class extending `Tests\TestCase`
- [ ] Test that NSFW prompts are sanitized
- [ ] Test that safe prompts pass through unchanged
- [ ] Test edge cases (empty string, very long prompts)
- [ ] Tests pass: `php artisan test --filter=GeminiBrainServiceTest`
- [ ] Add docblocks explaining test purpose

**Test Examples:**
```php
public function test_sanitizes_nsfw_prompts(): void
{
    $service = new GeminiBrainService();
    $unsafe = "Generate explicit adult content";
    $safe = $service->sanitizePromptForImageGeneration($unsafe);
    
    $this->assertNotEquals($unsafe, $safe);
    $this->assertStringContainsString('safe', strtolower($safe));
}
```

**Resources:**
- [docs/guides/testing-guide.md](../docs/guides/testing-guide.md)
- [Laravel Testing Docs](https://laravel.com/docs/testing)

---

### 🟡 ISSUE-005: Standardize Layout Across Admin Pages
**Reference:** BUG-005 in [BUGS.md](../BUGS.md)

**Description:**  
Admin dashboard pages have inconsistent layouts (spacing, navigation, button styles). Create a standard Blade component and refactor pages to use it. This can be tackled page-by-page.

**Difficulty:** 🟡 Medium  
**Time Estimate:** 3-4 hours (split across multiple PRs)  
**Skills Required:**
- Tailwind CSS
- Blade templates
- Blade components
- Eye for UI consistency

**Files to Modify:**
- Create: `resources/views/components/admin-layout.blade.php`
- Refactor: `resources/views/livewire/dashboard.blade.php`
- Refactor: `resources/views/livewire/persona-manager.blade.php`
- Refactor: Other admin pages

**Phase 1 Acceptance Criteria (First PR):**
- [ ] Create reusable `<x-admin-layout>` component with:
  - Consistent header with navigation
  - Consistent padding/spacing (p-6, max-w-7xl mx-auto)
  - Consistent button styles
  - Consistent card/panel styles
- [ ] Refactor Dashboard page to use component
- [ ] Screenshot before/after comparison

**Phase 2-N:**
- Refactor remaining pages one-by-one in separate PRs

**Resources:**
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Blade Components](https://laravel.com/docs/blade#components)
- [BUGS.md](../BUGS.md#bug-005-inconsistent-layout-between-web-pages)

---

### 🔴 ISSUE-006: Add Composite Index on event_schedules
**Reference:** Quick Win #5 in [TECHNICAL_DEBT.md](../TECHNICAL_DEBT.md)

**Description:**  
The `event_schedules` table is queried every minute by cron job but lacks an index on `scheduled_at`. Add a composite index to improve query performance.

**Difficulty:** 🟢 Easy  
**Time Estimate:** 10 minutes  
**Skills Required:**
- Laravel migrations
- Database indexing concepts

**Files to Create:**
- `database/migrations/YYYY_MM_DD_HHMMSS_add_index_to_event_schedules.php`

**Migration Code:**
```php
Schema::table('event_schedules', function (Blueprint $table) {
    $table->index(['scheduled_at', 'status'], 'event_schedules_scheduled_status_index');
});
```

**Acceptance Criteria:**
- [ ] Create migration with proper naming
- [ ] Add composite index on `(scheduled_at, status)`
- [ ] Run migration: `php artisan migrate`
- [ ] Verify index exists: `SHOW INDEXES FROM event_schedules;`
- [ ] Add down() method to drop index

**Resources:**
- [Laravel Migrations](https://laravel.com/docs/migrations#indexes)
- [MySQL Indexing](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html)

---

## 📋 Claiming an Issue

To work on an issue:

1. **Comment on the issue** in GitHub: "I'd like to work on this!"
2. **Wait for assignment** from a maintainer (usually <24 hours)
3. **Fork the repo** and create a branch
4. **Work on the issue** following [CONTRIBUTING.md](../CONTRIBUTING.md)
5. **Submit PR** when ready

## ❓ Getting Help

- **Stuck?** Comment on the issue with your question
- **Need clarification?** Ask in the issue thread
- **Want to discuss approach?** Open a GitHub Discussion
- **Found something unclear?** That's valuable feedback - let us know!

---

## 🎯 After Your First Contribution

Once you've completed your first issue, you can:

1. **Pick a more challenging issue** from the main issue tracker
2. **Propose a new feature** you'd like to build
3. **Review others' PRs** to learn and help
4. **Improve documentation** based on your experience

Welcome to the community! 🚀

---

**Questions?** Open a [GitHub Discussion](https://github.com/tohawk89/persona/discussions) or ask in any issue thread.
