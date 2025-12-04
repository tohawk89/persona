<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\EventSchedule;
use App\Models\MemoryTag;
use App\Facades\GeminiBrain;
use Illuminate\Support\Facades\Auth;

class PersonaDashboard extends Component
{
    public Persona $persona;
    public $loading = false;

    public function mount(Persona $persona)
    {
        // Authorization: Ensure user owns this persona
        if ($persona->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to persona.');
        }

        $this->persona = $persona;
    }

    public function triggerWakeUpRoutine()
    {
        $this->loading = true;

        try {
            // Generate daily plan with outfit selection
            $planData = GeminiBrain::generateDailyPlan(
                $this->persona->memoryTags,
                $this->persona->system_prompt,
                $this->persona->wake_time,
                $this->persona->sleep_time
            );

            $events = $planData['events'];
            $dailyOutfit = $planData['daily_outfit'];
            $nightOutfit = $planData['night_outfit'];

            // Save events to database
            foreach ($events as $event) {
                EventSchedule::create([
                    'persona_id' => $this->persona->id,
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
                        'persona_id' => $this->persona->id,
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
                        'persona_id' => $this->persona->id,
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
        $nextEvent = EventSchedule::where('persona_id', $this->persona->id)
            ->where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->first();

        $user = Auth::user();
        $lastInteraction = $user->last_interaction_at;

        return view('livewire.persona-dashboard', [
            'persona' => $this->persona,
            'nextEvent' => $nextEvent,
            'lastInteraction' => $lastInteraction,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
