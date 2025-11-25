<?php

namespace App\Services;

use App\Models\User;
use App\Models\EventSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmartQueueService
{
    /**
     * Time window (in minutes) to consider a user as "actively chatting"
     */
    private const ACTIVE_CHAT_WINDOW_MINUTES = 15;

    /**
     * Time to reschedule (in minutes) if user is active
     */
    private const RESCHEDULE_DELAY_MINUTES = 30;

    /**
     * Check if a user is currently in an active conversation.
     *
     * @param User $user
     * @return bool
     */
    public function isUserActive(User $user): bool
    {
        if (!$user->last_interaction_at) {
            return false;
        }

        $threshold = Carbon::now()->subMinutes(self::ACTIVE_CHAT_WINDOW_MINUTES);
        $isActive = Carbon::parse($user->last_interaction_at)->isAfter($threshold);

        Log::debug('SmartQueueService: Checking user activity', [
            'user_id' => $user->id,
            'last_interaction_at' => $user->last_interaction_at,
            'is_active' => $isActive,
        ]);

        return $isActive;
    }

    /**
     * Check if an event should be executed or rescheduled.
     * Returns true if event should be executed, false if it should be rescheduled.
     *
     * @param EventSchedule $event
     * @return bool
     */
    public function shouldExecuteEvent(EventSchedule $event): bool
    {
        $user = $event->persona->user;

        if (!$user) {
            Log::warning('SmartQueueService: Event has no associated user', [
                'event_id' => $event->id,
            ]);
            return true; // Execute anyway if no user context
        }

        $isActive = $this->isUserActive($user);

        if ($isActive) {
            Log::info('SmartQueueService: User is active, event will be rescheduled', [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]);
        }

        return !$isActive;
    }

    /**
     * Reschedule an event by adding delay minutes to its scheduled time.
     *
     * @param EventSchedule $event
     * @param int|null $delayMinutes If null, uses default RESCHEDULE_DELAY_MINUTES
     * @return void
     */
    public function rescheduleEvent(EventSchedule $event, ?int $delayMinutes = null): void
    {
        $delayMinutes = $delayMinutes ?? self::RESCHEDULE_DELAY_MINUTES;

        $newScheduledAt = Carbon::parse($event->scheduled_at)
            ->addMinutes($delayMinutes);

        $event->update([
            'scheduled_at' => $newScheduledAt,
            'status' => 'rescheduled',
        ]);

        Log::info('SmartQueueService: Event rescheduled', [
            'event_id' => $event->id,
            'original_time' => $event->scheduled_at,
            'new_time' => $newScheduledAt,
            'delay_minutes' => $delayMinutes,
        ]);
    }

    /**
     * Process an event with smart queue logic.
     * Returns true if event was executed, false if it was rescheduled.
     *
     * @param EventSchedule $event
     * @param callable $executeCallback Callback to execute the event
     * @return bool True if executed, false if rescheduled
     */
    public function processEvent(EventSchedule $event, callable $executeCallback): bool
    {
        if ($this->shouldExecuteEvent($event)) {
            // Execute the event
            try {
                $executeCallback($event);

                $event->update(['status' => 'sent']);

                Log::info('SmartQueueService: Event executed successfully', [
                    'event_id' => $event->id,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('SmartQueueService: Event execution failed', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);

                $event->update(['status' => 'failed']);

                return false;
            }
        } else {
            // Reschedule the event
            $this->rescheduleEvent($event);
            return false;
        }
    }

    /**
     * Get all pending events that are due for execution.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDueEvents()
    {
        return EventSchedule::where('status', 'pending')
            ->where('scheduled_at', '<=', Carbon::now())
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Get events that were rescheduled and are now due.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRescheduledDueEvents()
    {
        return EventSchedule::where('status', 'rescheduled')
            ->where('scheduled_at', '<=', Carbon::now())
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Update user's last interaction timestamp.
     *
     * @param User $user
     * @return void
     */
    public function updateUserInteraction(User $user): void
    {
        $user->update([
            'last_interaction_at' => Carbon::now(),
        ]);

        Log::debug('SmartQueueService: User interaction updated', [
            'user_id' => $user->id,
            'timestamp' => $user->last_interaction_at,
        ]);
    }

    /**
     * Check if it's within the persona's active hours (between wake_time and sleep_time).
     *
     * @param \App\Models\Persona $persona
     * @return bool
     */
    public function isWithinActiveHours($persona): bool
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        $wakeTime = $persona->wake_time;
        $sleepTime = $persona->sleep_time;

        // Handle case where sleep time is past midnight
        if ($sleepTime < $wakeTime) {
            $isActive = $currentTime >= $wakeTime || $currentTime < $sleepTime;
        } else {
            $isActive = $currentTime >= $wakeTime && $currentTime < $sleepTime;
        }

        Log::debug('SmartQueueService: Checking active hours', [
            'persona_id' => $persona->id,
            'current_time' => $currentTime,
            'wake_time' => $wakeTime,
            'sleep_time' => $sleepTime,
            'is_active' => $isActive,
        ]);

        return $isActive;
    }
}
