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
        public Message $incomingMessage,
        public ?string $imagePath = null
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

            // Generate AI response with media processing (pass image path for vision)
            $response = GeminiBrain::generateChatResponse(
                $chatHistory,
                $memoryTags,
                $persona->system_prompt,
                $persona,
                $this->imagePath
            );

            // Process media tags and send appropriate messages
            // NOTE: sendResponseToTelegram() handles saving to DB (CRITICAL for context)
            $this->sendResponseToTelegram($response);

            // Cleanup: Delete temporary image file after processing
            if ($this->imagePath && file_exists($this->imagePath)) {
                unlink($this->imagePath);
                Log::info('ProcessChatResponse: Temp image file deleted', [
                    'path' => $this->imagePath,
                ]);
            }

            Log::info('ProcessChatResponse: Response sent successfully', [
                'user_id' => $this->user->id,
                'response_length' => strlen($response),
                'had_image' => $this->imagePath !== null,
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
     * CRITICAL: Bot messages are ALWAYS saved to DB for context continuity.
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

        // CASE A: Image Tag
        if ($hasImage) {
            $imageUrl = trim($imageMatch[1]);

            // Send "Uploading Photo" action
            Telegram::sendChatAction($this->user->telegram_chat_id, 'upload_photo');

            Telegram::sendPhoto(
                $this->user->telegram_chat_id,
                $imageUrl,
                $textPart ?: null // Use text as caption
            );

            // CRITICAL: Save bot message with image to DB
            Message::create([
                'user_id' => $this->user->id,
                'persona_id' => $this->user->persona?->id,
                'sender_type' => 'bot',
                'content' => $textPart ?: '[Image]',
                'image_path' => $imageUrl,
            ]);
        }

        // CASE B: Voice Tag
        if ($hasAudio) {
            $audioUrl = trim($audioMatch[1]);

            // Send "Record Voice" action
            Telegram::sendChatAction($this->user->telegram_chat_id, 'record_voice');

            Telegram::sendVoice($this->user->telegram_chat_id, $audioUrl);

            // CRITICAL: Save bot message with voice to DB
            Message::create([
                'user_id' => $this->user->id,
                'persona_id' => $this->user->persona?->id,
                'sender_type' => 'bot',
                'content' => '[Voice Note]',
            ]);
        }

        // CASE C: Standard Text (only if no image, since image already sent text as caption)
        if (!$hasImage && !$hasAudio && $textPart) {
            // Plain text message with streaming effect
            Telegram::sendStreamingMessage($this->user->telegram_chat_id, $textPart);

            // CRITICAL: Save bot text response to DB
            Message::create([
                'user_id' => $this->user->id,
                'persona_id' => $this->user->persona?->id,
                'sender_type' => 'bot',
                'content' => $textPart,
            ]);
        } elseif (!$hasImage && $hasAudio && $textPart) {
            // If only audio (no image to use text as caption), send text separately
            Telegram::sendMessage($this->user->telegram_chat_id, $textPart);

            // CRITICAL: Save bot text response to DB (in addition to voice note record above)
            Message::create([
                'user_id' => $this->user->id,
                'persona_id' => $this->user->persona?->id,
                'sender_type' => 'bot',
                'content' => $textPart,
            ]);
        }
    }
}
