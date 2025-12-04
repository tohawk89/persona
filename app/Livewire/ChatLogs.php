<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class ChatLogs extends Component
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

    public function render()
    {
        $messages = Message::where('persona_id', $this->persona->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('livewire.chat-logs', [
            'messages' => $messages,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
