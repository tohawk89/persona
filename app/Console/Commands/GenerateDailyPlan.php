<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\{Persona, EventSchedule, MemoryTag};
use App\Facades\GeminiBrain;

class GenerateDailyPlan extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:generate-daily-plan {--persona-id= : Specific persona ID, or all if not provided}';

    /**
     * The console command description.
     */
    protected $description = 'Generate daily event plans for active personas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌅 Generating Daily Plans...');
        $this->newLine();

        // Get personas to process
        $query = Persona::where('is_active', true);

        if ($personaId = $this->option('persona-id')) {
            $query->where('id', $personaId);
        }

        $personas = $query->get();

        if ($personas->isEmpty()) {
            $this->warn('No active personas found.');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($personas as $persona) {
            $this->info("Processing: {$persona->name} (ID: {$persona->id})");

            try {
                // Delete old pending events for today
                EventSchedule::where('persona_id', $persona->id)
                    ->where('status', 'pending')
                    ->whereDate('scheduled_at', today())
                    ->delete();

                // Generate daily plan
                $planData = GeminiBrain::generateDailyPlan(
                    $persona->memoryTags,
                    $persona->system_prompt,
                    $persona->wake_time,
                    $persona->sleep_time
                );

                // Save outfit choices to memory tags
                if (!empty($planData['daily_outfit'])) {
                    MemoryTag::updateOrCreate(
                        [
                            'persona_id' => $persona->id,
                            'category' => 'daily_outfit',
                            'target' => 'self',
                        ],
                        [
                            'value' => $planData['daily_outfit'],
                            'context' => 'Set on ' . now()->format('Y-m-d H:i'),
                        ]
                    );
                }

                if (!empty($planData['night_outfit'])) {
                    MemoryTag::updateOrCreate(
                        [
                            'persona_id' => $persona->id,
                            'category' => 'night_outfit',
                            'target' => 'self',
                        ],
                        [
                            'value' => $planData['night_outfit'],
                            'context' => 'Set on ' . now()->format('Y-m-d H:i'),
                        ]
                    );
                }

                // Save events to database
                $eventCount = 0;
                foreach ($planData['events'] as $eventData) {
                    EventSchedule::create([
                        'persona_id' => $persona->id,
                        'type' => $eventData['type'] === 'image_generation' ? 'image_generation' : 'text',
                        'context_prompt' => $eventData['content'],
                        'scheduled_at' => $eventData['scheduled_at'],
                        'status' => 'pending',
                    ]);
                    $eventCount++;
                }

                $this->line("  ✓ Created {$eventCount} events");
                $this->line("  ✓ Outfit: {$planData['daily_outfit']}");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");

                Log::error('GenerateDailyPlan: Failed to generate plan', [
                    'persona_id' => $persona->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $failCount++;
            }

            $this->newLine();
        }

        // Summary
        $this->info('📊 Summary:');
        $this->line("  Success: {$successCount}");
        $this->line("  Failed: {$failCount}");

        return Command::SUCCESS;
    }
}
