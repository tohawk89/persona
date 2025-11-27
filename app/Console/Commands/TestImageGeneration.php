<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Persona;
use App\Facades\GeminiBrain;
use App\Facades\Telegram;
use Illuminate\Support\Facades\Log;

class TestImageGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:image {--prompt= : Custom prompt for image generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test image generation and send to Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎨 Testing Image Generation...');
        $this->newLine();

        // Get the first persona
        $persona = Persona::with('user')->first();

        if (!$persona) {
            $this->error('❌ No persona found in database');
            return self::FAILURE;
        }

        $this->info("✓ Found persona: {$persona->name} (ID: {$persona->id})");

        if (!$persona->user || !$persona->user->telegram_chat_id) {
            $this->error('❌ Persona has no user or telegram_chat_id configured');
            return self::FAILURE;
        }

        $chatId = $persona->user->telegram_chat_id;
        $this->info("✓ Telegram Chat ID: {$chatId}");
        $this->newLine();

        // Build prompt
        $customPrompt = $this->option('prompt');

        if ($customPrompt) {
            $prompt = $customPrompt;
            $this->info("📝 Using custom prompt: {$prompt}");
        } else {
            $prompt = "A young woman taking a cheerful selfie, smiling at the camera in a bright, modern room with natural lighting";
            $this->info("📝 Using default prompt: {$prompt}");
        }

        $this->newLine();
        $this->info('🔄 Generating image...');

        // Generate image
        $startTime = microtime(true);
        $imageUrl = GeminiBrain::generateImage($prompt, $persona);
        $duration = round(microtime(true) - $startTime, 2);

        if (!$imageUrl) {
            $this->error('❌ Image generation failed');
            $this->info('💡 Check logs for details: storage/logs/laravel.log');
            return self::FAILURE;
        }

        $this->info("✓ Image generated in {$duration}s");
        $this->info("✓ Image URL: {$imageUrl}");
        $this->newLine();

        // Send to Telegram
        $this->info('📤 Sending to Telegram...');

        $caption = "✨ Test Image Generated\n";
        $caption .= "Driver: " . config('services.image_generator.default', 'unknown') . "\n";
        $caption .= "Time: {$duration}s\n";
        $caption .= "Prompt: {$prompt}";

        $success = Telegram::sendPhoto($chatId, $imageUrl, $caption);

        if ($success) {
            $this->info('✓ Image sent successfully to Telegram!');
            $this->newLine();
            $this->info('🎉 Test completed successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to send image to Telegram');
            $this->info('💡 Check logs for details: storage/logs/laravel.log');
            return self::FAILURE;
        }
    }
}
