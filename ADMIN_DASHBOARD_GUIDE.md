# Admin Dashboard Guide

## Overview
The admin dashboard provides a comprehensive interface for managing your AI Virtual Companion system. Built with Livewire 3 and Tailwind CSS.

## Available Pages

### 1. Dashboard (`/dashboard`)
**Route:** `dashboard`  
**Component:** `App\Livewire\Dashboard`

**Features:**
- **Persona Status Card**: Shows if persona is Active/Inactive with wake/sleep times
- **Next Scheduled Event**: Displays upcoming event time and type
- **Last User Interaction**: Shows when user last interacted with the bot
- **Trigger Wake-Up Routine Button**: Manually generate daily plan (calls `GeminiBrain::generateDailyPlan()`)

**Key Methods:**
- `triggerWakeUpRoutine()`: Generates 5 events for the day and saves to database

---

### 2. Persona Manager (`/persona-manager`)
**Route:** `persona.manager`  
**Component:** `App\Livewire\PersonaManager`

**Features:**
- Edit persona configuration
- System Prompt (textarea, required, min 10 chars)
- Physical Traits (textarea, optional) - used for image generation consistency
- Wake Time and Sleep Time (time inputs, required)
- Avatar Reference Image upload (max 5MB, stored in `storage/app/public/avatars/`)

**Key Methods:**
- `mount()`: Loads existing persona or sets defaults
- `save()`: Creates or updates persona, handles file upload

**File Upload:**
Uses Livewire's `WithFileUploads` trait. Uploaded files are stored in `public/avatars/` and accessible via `Storage::url()`.

---

### 3. Memory Brain (`/memory-brain`)
**Route:** `memory.brain`  
**Component:** `App\Livewire\MemoryBrain`

**Features:**
- Table view of all memory tags
- Columns: Category, Target, Value, Context, Created At, Actions
- Add New Memory button (opens modal)
- Edit button (opens modal with existing data)
- Delete button (with confirmation)

**Key Methods:**
- `openModal($id = null)`: Opens modal for add/edit
- `closeModal()`: Closes modal and resets form
- `save()`: Creates or updates memory tag
- `delete($id)`: Deletes memory tag

**Fields:**
- Category: e.g., "preference", "fact", "interest"
- Target: "user" or "persona"
- Value: The actual memory content
- Context: Optional additional notes

---

### 4. Schedule Timeline (`/schedule-timeline`)
**Route:** `schedule.timeline`  
**Component:** `App\Livewire\ScheduleTimeline`

**Features:**
- Shows all events scheduled for today
- Time display with "X minutes ago" relative time
- Event type badges (Text/Image) with color coding
- Status badges (Pending/Sent/Cancelled) with color coding
- Cancel Event button (only for pending events)

**Key Methods:**
- `cancelEvent($id)`: Sets event status to 'cancelled'

**Filters:**
- Only shows events where `scheduled_at` is Today
- Ordered by `scheduled_at` ascending

---

### 5. Chat Logs (`/chat-logs`)
**Route:** `chat.logs`  
**Component:** `App\Livewire\ChatLogs`

**Features:**
- Chat-style interface displaying message history
- User messages: Right-aligned, blue background
- Bot messages: Left-aligned, gray background
- Timestamp below each message
- Scrollable (max height 600px)

**Styling:**
- User: `bg-blue-600 text-white justify-end`
- Bot: `bg-gray-200 dark:bg-gray-700 justify-start`

---

## Navigation

### Desktop Navigation
All 5 pages are accessible from the top navigation bar:
- Dashboard
- Persona
- Memory
- Schedule
- Chat Logs

### Mobile Navigation
Hamburger menu provides access to all pages via responsive nav links.

---

## Usage Flow

### Initial Setup
1. Create admin user: `php artisan app:create-admin`
2. Login at `/login`
3. Go to **Persona Manager** and configure your persona
4. Return to **Dashboard** and click "Trigger Wake-Up Routine"
5. Check **Schedule Timeline** to see generated events

### Daily Management
- **Dashboard**: Monitor status and manually trigger daily plans
- **Persona Manager**: Adjust persona behavior and appearance settings
- **Memory Brain**: Add/edit memories to enrich conversations
- **Schedule Timeline**: Review and cancel scheduled events
- **Chat Logs**: Review conversation history

---

## Technical Details

### Authentication
All admin routes require `auth` and `verified` middleware.

### Data Flow
- **Dashboard** → Reads from `personas`, `event_schedules`, `users`
- **Persona Manager** → CRUD on `personas` table
- **Memory Brain** → CRUD on `memory_tags` table
- **Schedule Timeline** → Reads from `event_schedules`, updates status
- **Chat Logs** → Reads from `messages` table

### File Storage
Avatar images are stored in `storage/app/public/avatars/` and symlinked to `public/storage/avatars/`.

Make sure to run:
```bash
php artisan storage:link
```

---

## Routes Summary

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('persona-manager', \App\Livewire\PersonaManager::class)->name('persona.manager');
    Route::get('memory-brain', \App\Livewire\MemoryBrain::class)->name('memory.brain');
    Route::get('schedule-timeline', \App\Livewire\ScheduleTimeline::class)->name('schedule.timeline');
    Route::get('chat-logs', \App\Livewire\ChatLogs::class)->name('chat.logs');
});
```

---

## Color Scheme

### Status Colors
- **Active/Success**: Green (`bg-green-600`, `text-green-600`)
- **Inactive/Error**: Red (`bg-red-600`, `text-red-600`)
- **Pending/Warning**: Yellow (`bg-yellow-600`, `text-yellow-600`)
- **Info**: Blue (`bg-blue-600`, `text-blue-600`)

### Message Types
- **Text**: Blue badges
- **Image**: Purple badges

### Dark Mode
All components fully support dark mode with appropriate color variants.

---

## Next Steps

To complete the system:
1. Implement TelegramWebhookController
2. Create Jobs (ProcessChatResponse, ExtractMemoryTags, ProcessScheduledEvent)
3. Create Console Commands (GenerateDailyPlan, ProcessScheduledEvents)
4. Configure Telegram webhook endpoint
5. Set environment variables (GEMINI_API_KEY, TELEGRAM_BOT_TOKEN)
