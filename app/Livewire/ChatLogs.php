<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class ChatLogs extends Component
{
    public function render()
    {
        $persona = Auth::user()->persona;

        $messages = $persona
            ? Message::where('persona_id', $persona->id)
                ->orderBy('created_at', 'asc')
                ->get()
            : collect();

        return view('livewire.chat-logs', [
            'messages' => $messages,
        ])->layout('layouts.app');
    }
}
