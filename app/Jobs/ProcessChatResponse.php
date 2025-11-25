<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\{User, Message};
use App\Facades\{GeminiBrain, Telegram};

class ProcessChatResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Message $incomingMessage
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $persona = $this->user->persona;

            if (!$persona) {
                Log::warning('ProcessChatResponse: User has no persona', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // Send typing indicator
            Telegram::sendChatAction($this->user->telegram_chat_id, 'typing');

            // Get recent chat history (last 20 messages)
            $chatHistory = Message::where('user_id', $this->user->id)
                ->latest()
                ->take(20)
                ->get()
                ->reverse()
                ->values();

            // Get memory tags
            $memoryTags = $persona->memoryTags;

            // Generate AI response with media processing
            $response = GeminiBrain::generateChatResponse(
                $chatHistory,
                $memoryTags,
                $persona->system_prompt,
                $persona
            );

            // Process media tags and send appropriate messages
            $this->sendResponseToTelegram($response);

            // Save bot response to database
            Message::create([
                'user_id' => $this->user->id,
                'persona_id' => $persona->id,
                'sender_type' => 'bot',
                'content' => $response,
            ]);

            Log::info('ProcessChatResponse: Response sent successfully', [
                'user_id' => $this->user->id,
                'response_length' => strlen($response),
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessChatResponse: Job failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send fallback message to user
            Telegram::sendMessage(
                $this->user->telegram_chat_id,
                "Adoi, ada masalah sikit... Cuba tanya sekali lagi? 💭"
            );

            throw $e; // Re-throw for retry logic
        }
    }

    /**
     * Send the response to Telegram, handling media tags.
     */
    private function sendResponseToTelegram(string $response): void
    {
        $hasImage = preg_match('/\[IMAGE:\s*(.+?)\]/', $response, $imageMatch);
        $hasAudio = preg_match('/\[AUDIO:\s*(.+?)\]/', $response, $audioMatch);

        // Extract clean text (remove all media tags)
        $textPart = $response;
        $textPart = preg_replace('/\[IMAGE:\s*.+?\]/', '', $textPart);
        $textPart = preg_replace('/\[AUDIO:\s*.+?\]/', '', $textPart);
        $textPart = trim($textPart);

        // Send image if present
        if ($hasImage) {
            $imageUrl = trim($imageMatch[1]);
            Telegram::sendPhoto(
                $this->user->telegram_chat_id,
                $imageUrl,
                $textPart ?: null // Use text as caption
            );
        }

        // Send audio if present
        if ($hasAudio) {
            $audioUrl = trim($audioMatch[1]);
            Telegram::sendVoice($this->user->telegram_chat_id, $audioUrl);
        }

        // Send remaining text only if there's no image (image already used it as caption)
        if (!$hasImage && !$hasAudio && $textPart) {
            // Plain text message with streaming effect
            Telegram::sendStreamingMessage($this->user->telegram_chat_id, $textPart);
        } elseif (!$hasImage && $hasAudio && $textPart) {
            // If only audio (no image to use text as caption), send text separately
            Telegram::sendMessage($this->user->telegram_chat_id, $textPart);
        }
    }
}
