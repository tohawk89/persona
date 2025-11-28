<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Persona, EventSchedule};
use App\Facades\{GeminiBrain, Telegram};

class TestEventSending extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:event-send {--type=text : Event type (text or image_generation)}';

    /**
     * The console command description.
     */
    protected $description = 'Test event sending to Telegram with proper formatting';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Event Sending to Telegram');
        $this->newLine();

        // Get persona
        $persona = Persona::with(['user'])->first();

        if (!$persona || !$persona->user) {
            $this->error('No persona or user found.');
            return Command::FAILURE;
        }

        $chatId = $persona->user->telegram_chat_id;

        if (!$chatId) {
            $this->error('No Telegram chat ID configured.');
            return Command::FAILURE;
        }

        $this->line("✓ Persona: {$persona->name}");
        $this->line("✓ Chat ID: {$chatId}");
        $this->newLine();

        $type = $this->option('type');

        // Create test event
        $instruction = $type === 'image_generation'
            ? 'Send a cheerful selfie. Mention feeling happy today.'
            : 'Send cheerful greeting. Ask how their day is going.';

        $this->line("📝 Event Type: {$type}");
        $this->line("📝 Instruction: {$instruction}");
        $this->newLine();

        // Create temporary event (don't save)
        $tempEvent = new EventSchedule([
            'persona_id' => $persona->id,
            'type' => $type,
            'context_prompt' => $instruction,
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        $this->line('🔄 Generating JIT response...');

        try {
            $generatedResponse = GeminiBrain::generateEventResponse($tempEvent, $persona);

            $this->newLine();
            $this->info('✅ Generated Response:');
            $this->line('─────────────────────────────────────────');
            $this->line($generatedResponse);
            $this->line('─────────────────────────────────────────');
            $this->newLine();

            // Check for tags
            $hasSplit = str_contains($generatedResponse, '<SPLIT>');
            $hasImage = str_contains($generatedResponse, '[IMAGE:');
            $hasMood = preg_match('/\[MOOD:\s*([^\]]+)\]/', $generatedResponse, $moodMatch);

            if ($hasSplit) {
                $splitCount = substr_count($generatedResponse, '<SPLIT>');
                $messageCount = $splitCount + 1;
                $this->line("📨 Contains {$splitCount} <SPLIT> tags (will send as {$messageCount} messages)");
            }
            if ($hasImage) {
                preg_match_all('/\[IMAGE:\s*([^\]]+)\]/', $generatedResponse, $imageMatches);
                $imageCount = count($imageMatches[0]);
                $this->line("🖼️  Contains {$imageCount} image(s)");
            }
            if ($hasMood) {
                $this->line("😊 Mood: {$moodMatch[1]}");
            }

            $this->newLine();
            $confirm = $this->confirm('Send this message to Telegram?', true);

            if (!$confirm) {
                $this->info('Cancelled.');
                return Command::SUCCESS;
            }

            $this->line('📤 Sending to Telegram...');

            $success = Telegram::sendStreamingMessage($chatId, $generatedResponse);

            if ($success) {
                $this->newLine();
                $this->info('✅ Message sent successfully!');
                $this->line('Check your Telegram to verify the message.');
            } else {
                $this->newLine();
                $this->error('❌ Failed to send message. Check logs for details.');
            }

            return $success ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
