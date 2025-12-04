<?php

namespace App\Livewire;

use App\Models\Persona;
use Livewire\Component;

class PersonaList extends Component
{
    public function render()
    {
        $personas = Persona::where('user_id', auth()->id())->get();

        return view('livewire.persona-list', [
            'personas' => $personas,
        ])->layout('layouts.app');
    }

    public function createPersona()
    {
        $persona = Persona::create([
            'user_id' => auth()->id(),
            'name' => 'New Persona',
            'system_prompt' => 'You are a friendly AI companion.',
            'wake_time' => '08:00',
            'sleep_time' => '23:00',
        ]);

        return redirect()->route('persona.edit', $persona);
    }
}
