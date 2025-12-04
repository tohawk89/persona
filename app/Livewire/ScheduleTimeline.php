<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\EventSchedule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ScheduleTimeline extends Component
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

    public function sendNow($id)
    {
        $event = EventSchedule::findOrFail($id);

        // Dispatch the job immediately
        \App\Jobs\ProcessScheduledEvent::dispatch($event);

        session()->flash('success', 'Event sent to queue! Check your Telegram in a moment.');
    }

    public function cancelEvent($id)
    {
        $event = EventSchedule::findOrFail($id);
        $event->update(['status' => 'cancelled']);
        session()->flash('success', 'Event cancelled successfully!');
    }

    public function testInChat($id)
    {
        $event = EventSchedule::findOrFail($id);

        // Store the event details in session to trigger in test chat
        session([
            'trigger_event_id' => $event->id,
            'trigger_event_type' => $event->type,
            'trigger_event_prompt' => $event->context_prompt,
            'trigger_event_time' => $event->scheduled_at->format('Y-m-d H:i:s'),
        ]);

        return redirect()->route('persona.test', $this->persona);
    }

    public function render()
    {
        $events = EventSchedule::where('persona_id', $this->persona->id)
            ->whereDate('scheduled_at', '>=', Carbon::today())
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return view('livewire.schedule-timeline', [
            'events' => $events,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
