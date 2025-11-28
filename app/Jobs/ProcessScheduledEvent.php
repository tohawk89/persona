<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\EventSchedule;
use App\Facades\{SmartQueue, Telegram};

class ProcessScheduledEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EventSchedule $event
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Use SmartQueue to determine if event should execute or reschedule
            $executed = SmartQueue::processEvent($this->event, function($event) {
                $user = $event->persona->user;

                if (!$user || !$user->telegram_chat_id) {
                    Log::warning('ProcessScheduledEvent: User has no Telegram chat ID', [
                        'event_id' => $event->id,
                    ]);
                    return;
                }

                // ===== JUST-IN-TIME GENERATION =====
                // Generate response dynamically based on current context
                $generatedResponse = \App\Facades\GeminiBrain::generateEventResponse(
                    $event,
                    $event->persona
                );

                Log::info('ProcessScheduledEvent: JIT response generated', [
                    'event_id' => $event->id,
                    'instruction' => $event->context_prompt,
                    'generated_length' => strlen($generatedResponse),
                ]);

                // Send the generated response (handles both text and image events)
                // The sendStreamingMessage method will handle [IMAGE:] tags automatically
                Telegram::sendStreamingMessage(
                    $user->telegram_chat_id,
                    $generatedResponse
                );

                // Save the generated message to database for context
                $event->persona->messages()->create([
                    'user_id' => $user->id,
                    'persona_id' => $event->persona->id,
                    'sender_type' => 'bot',
                    'content' => strip_tags(preg_replace('/\[(IMAGE|AUDIO|MOOD|NO_REPLY):.*?\]/', '', $generatedResponse)),
                    'is_event_trigger' => true,
                ]);

                // Mark event as sent
                $event->update(['status' => 'sent']);

                Log::info('ProcessScheduledEvent: Event sent successfully', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                ]);
            });

            if (!$executed) {
                Log::info('ProcessScheduledEvent: Event rescheduled due to user activity', [
                    'event_id' => $this->event->id,
                    'new_scheduled_at' => $this->event->fresh()->scheduled_at,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ProcessScheduledEvent: Job failed', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark event as cancelled after max retries
            if ($this->attempts() >= $this->tries) {
                $this->event->update(['status' => 'cancelled']);
            }

            throw $e; // Re-throw for retry logic
        }
    }
}
