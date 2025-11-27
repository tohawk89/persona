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
     *
     * @param User $user The user to process chat for
     * @param string|null $imagePath Optional image path (bypasses buffering)
     */
    public function __construct(
        public User $user,
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

            // STEP 1: Atomic Lock (Prevent concurrent processing)
            $lockKey = "processing_chat_{$this->user->id}";
            $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

            if (!$lock->get()) {
                Log::info('ProcessChatResponse: Another instance is processing, skipping', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            try {
                // STEP 2: Fetch & Clear Buffer (or use image path)
                $aggregatedText = null;

                if ($this->imagePath) {
                    // Image path provided: process immediately without buffer
                    $aggregatedText = null; // Will use latest message
                } else {
                    // Text message: fetch from buffer
                    $bufferKey = "chat_buffer_{$this->user->telegram_chat_id}";
                    $aggregatedText = \Illuminate\Support\Facades\Cache::pull($bufferKey);

                    if (empty($aggregatedText)) {
                        Log::info('ProcessChatResponse: Buffer empty, already processed', [
                            'user_id' => $this->user->id,
                        ]);
                        $lock->release();
                        return;
                    }

                    Log::info('ProcessChatResponse: Processing buffered messages', [
                        'user_id' => $this->user->id,
                        'aggregated_length' => strlen($aggregatedText),
                        'message_count' => substr_count($aggregatedText, "\n") + 1,
                    ]);
                }

                // Send typing indicator
                Telegram::sendChatAction($this->user->telegram_chat_id, 'typing');

                // STEP 3: Get chat history (last 20 messages excluding the current buffer)
                $chatHistory = Message::where('user_id', $this->user->id)
                    ->latest()
                    ->take(20)
                    ->get()
                    ->reverse()
                    ->values();

                // Get memory tags
                $memoryTags = $persona->memoryTags;

                // STEP 4: Generate AI response
                if ($aggregatedText) {
                    // Create a temporary message object for buffered text
                    $bufferMessage = new Message([
                        'user_id' => $this->user->id,
                        'persona_id' => $persona->id,
                        'sender_type' => 'user',
                        'content' => $aggregatedText,
                        'created_at' => now(),
                    ]);

                    // Append to history for context
                    $chatHistory->push($bufferMessage);
                }

                $response = GeminiBrain::generateChatResponse(
                    $chatHistory,
                    $memoryTags,
                    $persona->system_prompt,
                    $persona,
                    $this->imagePath
                );

                // STEP 4.5: Check for [NO_REPLY] tag (conversation end)
                if (trim($response) === '[NO_REPLY]') {
                    Log::info('ProcessChatResponse: Bot chose to end conversation (no reply sent)', [
                        'user_id' => $this->user->id,
                    ]);

                    // Cleanup temp image file if exists
                    if ($this->imagePath && file_exists($this->imagePath)) {
                        unlink($this->imagePath);
                    }

                    return; // Exit early without sending message
                }

                // STEP 4.6: Extract and save real-time mood
                if (preg_match('/\[MOOD:\s*(.+?)\]/', $response, $moodMatch)) {
                    $moodValue = trim($moodMatch[1]);

                    // Update or create current_mood memory tag
                    \App\Models\MemoryTag::updateOrCreate(
                        [
                            'persona_id' => $persona->id,
                            'category' => 'current_mood',
                            'target' => 'self',
                        ],
                        [
                            'value' => $moodValue,
                            'context' => 'Real-time update on ' . now()->format('Y-m-d H:i:s'),
                        ]
                    );

                    // Remove mood tag from response (hidden from user)
                    $response = str_replace($moodMatch[0], '', $response);
                    $response = trim($response);

                    Log::info('ProcessChatResponse: Mood extracted and saved', [
                        'user_id' => $this->user->id,
                        'mood' => $moodValue,
                    ]);
                }

                // STEP 5: Send response to Telegram
                // NOTE: sendResponseToTelegram() handles saving to DB (CRITICAL for context)
                $this->sendResponseToTelegram($response);

                // STEP 6: Cleanup temp image file
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
                    'was_buffered' => $aggregatedText !== null,
                ]);

            } finally {
                // Always release the lock
                $lock->release();
            }

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
     * Send the response to Telegram, handling media tags and message splitting.
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

        // CASE C: Standard Text (with natural pacing via <SPLIT> delimiter)
        if (!$hasImage && !$hasAudio && $textPart) {
            // Split text by <SPLIT> delimiter for natural message pacing
            $parts = explode('<SPLIT>', $textPart);

            foreach ($parts as $index => $part) {
                $part = trim($part);

                // Skip empty parts
                if (empty($part)) {
                    continue;
                }

                // Send typing indicator before each message
                Telegram::sendChatAction($this->user->telegram_chat_id, 'typing');

                // Calculate human-like delay based on message length
                // Formula: 0.05 seconds per character, capped between 1-4 seconds
                $delay = min(max(strlen($part) * 0.05, 1), 4);
                sleep((int) $delay);

                // Send the message part
                Telegram::sendMessage($this->user->telegram_chat_id, $part);

                // CRITICAL: Save each part to DB for context continuity
                Message::create([
                    'user_id' => $this->user->id,
                    'persona_id' => $this->user->persona?->id,
                    'sender_type' => 'bot',
                    'content' => $part,
                ]);

                Log::info('ProcessChatResponse: Message part sent', [
                    'user_id' => $this->user->id,
                    'part_index' => $index + 1,
                    'total_parts' => count($parts),
                    'length' => strlen($part),
                    'delay' => $delay,
                ]);
            }
        } elseif (!$hasImage && $hasAudio && $textPart) {
            // If only audio (no image to use text as caption), split and send text separately
            $parts = explode('<SPLIT>', $textPart);

            foreach ($parts as $index => $part) {
                $part = trim($part);

                if (empty($part)) {
                    continue;
                }

                Telegram::sendChatAction($this->user->telegram_chat_id, 'typing');
                $delay = min(max(strlen($part) * 0.05, 1), 4);
                sleep((int) $delay);

                Telegram::sendMessage($this->user->telegram_chat_id, $part);

                Message::create([
                    'user_id' => $this->user->id,
                    'persona_id' => $this->user->persona?->id,
                    'sender_type' => 'bot',
                    'content' => $part,
                ]);
            }
        }
    }
}
