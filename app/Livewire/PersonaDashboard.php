<?php

namespace App\Livewire;

use App\Facades\Brain;
use App\Models\EventSchedule;
use App\Models\Persona;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PersonaDashboard extends Component
{
    public Persona $persona;

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
        try {
            // Generate daily plan
            $planData = Brain::generateDailyPlan(
                $this->persona->memoryTags,
                $this->persona->system_prompt,
                $this->persona->wake_time,
                $this->persona->sleep_time
            );

            $events = $planData;

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

            session()->flash('success', 'Daily plan generated successfully! '.count($events).' events scheduled.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate daily plan: '.$e->getMessage());
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
