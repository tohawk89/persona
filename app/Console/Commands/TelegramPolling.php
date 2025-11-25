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

            if (empty($data['text'])) {
                return; // Skip non-text messages
            }

            $this->info("Message from {$data['user']['first_name']}: {$data['text']}");

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

            // Save message
            $message = Message::create([
                'user_id' => $user->id,
                'persona_id' => $user->persona?->id,
                'sender_type' => 'user',
                'content' => $data['text'],
            ]);

            $this->info('Message saved, dispatching response job...');

            // Dispatch response job
            ProcessChatResponse::dispatch($user, $message);

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
