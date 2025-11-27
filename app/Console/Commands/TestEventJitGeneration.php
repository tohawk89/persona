<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Persona, EventSchedule};
use App\Facades\GeminiBrain;

class TestEventJitGeneration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:event-jit {--instruction= : Custom event instruction}';

    /**
     * The console command description.
     */
    protected $description = 'Test just-in-time event generation with current mood and chat context';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🎯 Testing Just-In-Time Event Generation');
        $this->newLine();

        // Get the first persona
        $persona = Persona::with(['memoryTags', 'messages'])->first();

        if (!$persona) {
            $this->error('No persona found in database.');
            return Command::FAILURE;
        }

        $this->line("✓ Found persona: {$persona->name} (ID: {$persona->id})");

        // Show current mood
        $currentMood = $persona->memoryTags()
            ->where('category', 'current_mood')
            ->where('target', 'self')
            ->first();

        if ($currentMood) {
            $this->line("✓ Current Mood: {$currentMood->value}");
        } else {
            $this->line("⚠ No current mood set");
        }

        // Show recent chat context
        $recentMessages = $persona->messages()
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->reverse();

        if ($recentMessages->isNotEmpty()) {
            $this->line("✓ Recent Messages: {$recentMessages->count()}");
            foreach ($recentMessages as $msg) {
                $sender = $msg->sender_type === 'user' ? 'User' : 'Bot';
                $preview = substr($msg->content, 0, 50);
                $this->line("  - {$sender}: {$preview}...");
            }
        } else {
            $this->line("⚠ No recent messages");
        }

        $this->newLine();

        // Get instruction from option or use default
        $instruction = $this->option('instruction') ?? 'Send morning greeting. Ask how they slept.';
        $this->line("📝 Event Instruction: {$instruction}");
        $this->newLine();

        // Create a temporary event schedule for testing
        $tempEvent = new EventSchedule([
            'persona_id' => $persona->id,
            'type' => 'text',
            'context_prompt' => $instruction,
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        // Don't save it, just use for generation
        $this->line('🔄 Generating contextual response...');
        $this->newLine();

        try {
            $generatedResponse = GeminiBrain::generateEventResponse($tempEvent, $persona);

            $this->info('✅ Generated Response:');
            $this->line('─────────────────────────────────────────');
            $this->line($generatedResponse);
            $this->line('─────────────────────────────────────────');
            $this->newLine();

            // Show what tags are present
            if (str_contains($generatedResponse, '[GENERATE_IMAGE:')) {
                $this->line('📸 Contains image generation tag');
            }
            if (str_contains($generatedResponse, '[SEND_VOICE:')) {
                $this->line('🎤 Contains voice generation tag');
            }
            if (str_contains($generatedResponse, '[MOOD:')) {
                preg_match('/\[MOOD:\s*([^\]]+)\]/', $generatedResponse, $matches);
                if (isset($matches[1])) {
                    $this->line("😊 Detected Mood: {$matches[1]}");
                }
            }

            $this->newLine();
            $this->info('🎉 Test completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Generation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
