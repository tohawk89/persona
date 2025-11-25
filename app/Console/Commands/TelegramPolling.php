<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\{Telegram, SmartQueue};
use App\Models\{User, Message};
use App\Jobs\{ProcessChatResponse, ExtractMemoryTags};
use Illuminate\Support\Facades\Log;

class TelegramPolling extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Poll Telegram for updates (development mode)';

    private int $lastUpdateId = 0;

    public function handle(): int
    {
        $this->info('Starting Telegram polling...');
        $this->info('Press Ctrl+C to stop');

        // Remove webhook first
        Telegram::removeWebhook();
        $this->info('Webhook removed, using polling mode');

        while (true) {
            try {
                $updates = Telegram::getUpdates([
                    'offset' => $this->lastUpdateId + 1,
                    'timeout' => 30,
                ]);

                foreach ($updates as $update) {
                    $updateArray = $update->toArray();
                    $this->processUpdate($updateArray);
                    $this->lastUpdateId = $updateArray['update_id'];
                }

            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
                Log::error('TelegramPolling: Error processing updates', [
                    'error' => $e->getMessage(),
                ]);
                sleep(5); // Wait before retry
            }
        }

        return Command::SUCCESS;
    }

    private function processUpdate(array $update): void
    {
        try {
            // Parse the update
            $data = Telegram::parseUpdate($update);
            $message = $update['message'] ?? null;

            // Check for photo
            $imagePath = null;
            if ($message && isset($message['photo']) && is_array($message['photo'])) {
                // Get the largest photo (last item in array)
                $photo = end($message['photo']);
                $fileId = $photo['file_id'];

                try {
                    // Get file info from Telegram
                    $fileInfo = Telegram::getFile(['file_id' => $fileId]);
                    $filePath = $fileInfo['file_path'] ?? null;

                    if ($filePath) {
                        // Download image from Telegram
                        $botToken = config('services.telegram.bot_token');
                        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

                        $imageData = file_get_contents($fileUrl);

                        if ($imageData) {
                            // Save to temp storage
                            $uuid = \Illuminate\Support\Str::uuid();
                            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
                            $tempPath = storage_path("app/private/temp/{$uuid}.{$extension}");

                            // Ensure temp directory exists
                            if (!is_dir(storage_path('app/private/temp'))) {
                                mkdir(storage_path('app/private/temp'), 0755, true);
                            }

                            file_put_contents($tempPath, $imageData);
                            $imagePath = $tempPath;

                            $this->info("Downloaded photo: {$fileId} -> {$tempPath}");
                            Log::info('TelegramPolling: Downloaded photo', [
                                'file_id' => $fileId,
                                'temp_path' => $tempPath,
                                'size' => strlen($imageData),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to download photo: {$e->getMessage()}");
                    Log::error('TelegramPolling: Failed to download photo', [
                        'file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Get text or caption
            $text = $data['text'] ?? ($message['caption'] ?? null);

            // If photo but no caption, set default text
            if ($imagePath && empty($text)) {
                $text = '[Sent an image]';
            }

            // Skip if no text and no image
            if (empty($text) && !$imagePath) {
                return;
            }

            $this->info("Message from {$data['user']['first_name']}: {$text}" . ($imagePath ? ' [with image]' : ''));

            // Find or create user
            $user = User::firstOrCreate(
                ['telegram_chat_id' => $data['chat_id']],
                [
                    'name' => $data['user']['first_name'] ?? 'User',
                    'email' => "telegram_{$data['chat_id']}@placeholder.local",
                    'password' => bcrypt(str()->random(32)),
                ]
            );

            // Update interaction timestamp
            SmartQueue::updateUserInteraction($user);

            // Send typing indicator
            Telegram::sendChatAction($data['chat_id'], 'typing');

            // Save message
            $message = Message::create([
                'user_id' => $user->id,
                'persona_id' => $user->persona?->id,
                'sender_type' => 'user',
                'content' => $text,
                'image_path' => $imagePath,
            ]);

            $this->info('Message saved, dispatching response job...');

            // Dispatch response job with image path
            ProcessChatResponse::dispatch($user, $message, $imagePath);

            // Extract memories every 10 messages
            $messageCount = Message::where('user_id', $user->id)->count();
            if ($messageCount % 10 === 0) {
                ExtractMemoryTags::dispatch($user);
                $this->info('Memory extraction job dispatched');
            }

        } catch (\Exception $e) {
            $this->error('Failed to process update: ' . $e->getMessage());
            Log::error('TelegramPolling: Failed to process update', [
                'error' => $e->getMessage(),
                'update' => $update,
            ]);
        }
    }
}
