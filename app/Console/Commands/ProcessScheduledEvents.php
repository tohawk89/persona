<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\EventSchedule;
use App\Jobs\ProcessScheduledEvent;

class ProcessScheduledEvents extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:process-scheduled-events';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled events that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get all pending events that are due (scheduled_at <= now)
        $dueEvents = EventSchedule::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with(['persona.user'])
            ->get();

        if ($dueEvents->isEmpty()) {
            $this->info('No events due for processing.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$dueEvents->count()} due event(s)...");

        foreach ($dueEvents as $event) {
            try {
                // Dispatch job to process the event with SmartQueue logic
                ProcessScheduledEvent::dispatch($event);

                $this->line("  ✓ Dispatched event #{$event->id} ({$event->type})");

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to dispatch event #{$event->id}: {$e->getMessage()}");

                Log::error('ProcessScheduledEvents: Failed to dispatch event', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('✓ All due events dispatched to queue.');

        return Command::SUCCESS;
    }
}
