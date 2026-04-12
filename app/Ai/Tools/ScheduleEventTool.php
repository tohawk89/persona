<?php

namespace App\Ai\Tools;

use App\Models\EventSchedule;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ScheduleEventTool implements Tool
{
    public function __construct(private readonly Persona $persona) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        $currentTime = now()->format('Y-m-d H:i');

        return "Schedule a future message to the user. Use this PROACTIVELY when the user mentions future plans (meetings, waking up, travel, appointments). Current time is: {$currentTime}";
    }

    /**
     * Execute the tool — create the scheduled event in the database.
     */
    public function handle(Request $request): Stringable|string
    {
        $time = $request->get('time');
        $topic = $request->get('topic');

        try {
            $scheduledAt = Carbon::parse($time);

            EventSchedule::create([
                'persona_id' => $this->persona->id,
                'type' => 'text',
                'context_prompt' => "User has an event: {$topic}. Send a natural, caring message checking on them or wishing them luck.",
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
            ]);

            Log::info('ScheduleEventTool: Event scheduled', [
                'persona_id' => $this->persona->id,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'topic' => $topic,
            ]);

            return json_encode(['success' => true, 'message' => "Event scheduled for {$time}: {$topic}"]);
        } catch (\Throwable $e) {
            Log::error('ScheduleEventTool: Failed to schedule event', [
                'error' => $e->getMessage(),
                'time' => $time,
                'topic' => $topic,
            ]);

            return json_encode(['success' => false, 'message' => 'Failed to schedule event.']);
        }
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $currentTime = now()->format('Y-m-d H:i');

        return [
            'time' => $schema
                ->string()
                ->description("The time to send the message (Format: YYYY-MM-DD HH:MM). Convert relative times (like '2 PM today', 'tomorrow 9 AM') to absolute timestamp based on current time: {$currentTime}")
                ->required(),

            'topic' => $schema
                ->string()
                ->description('The context or topic of the message (e.g., "Wake up check", "Good luck for meeting", "Check on travel")')
                ->required(),
        ];
    }
}
