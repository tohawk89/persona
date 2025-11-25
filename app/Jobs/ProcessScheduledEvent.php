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

                // Send appropriate message type
                if ($event->type === 'text') {
                    Telegram::sendStreamingMessage(
                        $user->telegram_chat_id,
                        $event->context_prompt
                    );
                } elseif ($event->type === 'image_generation') {
                    // Show upload photo indicator
                    Telegram::sendChatAction($user->telegram_chat_id, 'upload_photo');

                    // Generate and send image
                    $imageUrl = \App\Facades\GeminiBrain::generateImage(
                        $event->context_prompt,
                        $event->persona
                    );

                    if ($imageUrl) {
                        Telegram::sendPhoto(
                            $user->telegram_chat_id,
                            $imageUrl,
                            "Here's something for you! 📸"
                        );
                    } else {
                        // Fallback to text if image generation fails
                        Telegram::sendMessage(
                            $user->telegram_chat_id,
                            "I wanted to share something special with you, but I'm having trouble with the image right now 😔"
                        );
                    }
                }

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
