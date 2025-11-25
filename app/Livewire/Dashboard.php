<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\EventSchedule;
use App\Models\MemoryTag;
use App\Models\User;
use App\Facades\GeminiBrain;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $loading = false;

    public function triggerWakeUpRoutine()
    {
        $this->loading = true;

        $user = Auth::user();
        $persona = $user->persona;

        if (!$persona) {
            session()->flash('error', 'No persona configured for this user.');
            $this->loading = false;
            return;
        }

        try {
            // Generate daily plan with outfit selection
            $planData = GeminiBrain::generateDailyPlan(
                $persona->memoryTags,
                $persona->system_prompt,
                $persona->wake_time,
                $persona->sleep_time
            );

            $events = $planData['events'];
            $dailyOutfit = $planData['daily_outfit'];
            $nightOutfit = $planData['night_outfit'];

            // Save events to database
            foreach ($events as $event) {
                EventSchedule::create([
                    'persona_id' => $persona->id,
                    'scheduled_at' => $event['scheduled_at'],
                    'type' => $event['type'],
                    'context_prompt' => $event['content'],
                    'status' => 'pending',
                ]);
            }

            // Save/Update daily outfit to memory_tags
            if ($dailyOutfit) {
                MemoryTag::updateOrCreate(
                    [
                        'persona_id' => $persona->id,
                        'category' => 'daily_outfit',
                    ],
                    [
                        'target' => 'self',
                        'value' => $dailyOutfit,
                        'context' => 'Morning routine on ' . now()->format('Y-m-d'),
                    ]
                );
            }

            // Save/Update night outfit to memory_tags
            if ($nightOutfit) {
                MemoryTag::updateOrCreate(
                    [
                        'persona_id' => $persona->id,
                        'category' => 'night_outfit',
                    ],
                    [
                        'target' => 'self',
                        'value' => $nightOutfit,
                        'context' => 'Morning routine on ' . now()->format('Y-m-d'),
                    ]
                );
            }

            session()->flash('success', 'Daily plan generated successfully! ' . count($events) . ' events scheduled.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate daily plan: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        $user = Auth::user();
        $persona = $user->persona;

        $nextEvent = null;
        $lastInteraction = null;

        if ($persona) {
            $nextEvent = EventSchedule::where('persona_id', $persona->id)
                ->where('status', 'pending')
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at', 'asc')
                ->first();
        }

        $lastInteraction = $user->last_interaction_at;

        return view('livewire.dashboard', [
            'persona' => $persona,
            'nextEvent' => $nextEvent,
            'lastInteraction' => $lastInteraction,
        ]);
    }
}
