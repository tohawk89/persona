<?php

namespace App\Jobs;

use App\Facades\GeminiBrain;
use App\Facades\Telegram;
use App\Models\Message;
use App\Models\Persona;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 minutes for image generation
    public int $tries = 2; // Retry once if it fails

    public function __construct(
        private readonly int $messageId,
        private readonly int $personaId,
        private readonly string $description,
        private readonly string $chatId,
    ) {}

    public function handle(): void
    {
        $message = Message::find($this->messageId);
        $persona = Persona::find($this->personaId);

        if (!$message || !$persona) {
            Log::error('GenerateImage: Message or persona not found', [
                'message_id' => $this->messageId,
                'persona_id' => $this->personaId,
            ]);
            return;
        }

        // Switch to persona's bot token
        $botToken = $persona->telegram_bot_token;
        if ($botToken) {
            Telegram::setToken($botToken);
        }

        Log::info('GenerateImage: Starting generation', [
            'message_id' => $this->messageId,
            'persona_id' => $this->personaId,
            'description' => $this->description,
        ]);

        try {
            // Generate image
            $imageUrl = GeminiBrain::generateImage($this->description, $persona);

            if (empty($imageUrl)) {
                Log::error('GenerateImage: Failed to generate image', [
                    'message_id' => $this->messageId,
                    'description' => $this->description,
                ]);

                // Update message with failure notice
                $message->update([
                    'content' => $message->content . "\n\n[Image generation failed]",
                ]);

                // Notify user
                Telegram::sendMessage($this->chatId, "Adoi, failed to generate image. Please try again later 🥺");
                return;
            }

            Log::info('GenerateImage: Image generated successfully', [
                'message_id' => $this->messageId,
                'image_url' => $imageUrl,
            ]);

            // Update message with image URL
            $message->update([
                'content' => str_replace('[IMAGE: pending]', "[IMAGE: {$imageUrl}]", $message->content),
                'image_path' => $imageUrl,
            ]);

            // Send image to user
            Telegram::sendPhoto($this->chatId, $imageUrl);

            Log::info('GenerateImage: Image sent to Telegram', [
                'message_id' => $this->messageId,
                'chat_id' => $this->chatId,
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateImage: Exception during generation', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update message with failure notice
            $message->update([
                'content' => $message->content . "\n\n[Image generation error: {$e->getMessage()}]",
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateImage: Job failed permanently', [
            'message_id' => $this->messageId,
            'persona_id' => $this->personaId,
            'description' => $this->description,
            'error' => $exception->getMessage(),
        ]);

        // Notify user about permanent failure
        $botToken = Persona::find($this->personaId)?->telegram_bot_token;
        if ($botToken) {
            Telegram::setToken($botToken);
        }

        Telegram::sendMessage($this->chatId, "Sorry, I couldn't generate the image even after retrying 🥺");
    }
}
