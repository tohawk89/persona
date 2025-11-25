<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MemoryTag;
use Illuminate\Support\Facades\Auth;

class MemoryBrain extends Component
{
    public $showModal = false;
    public $editingId = null;
    public $category;
    public $target;
    public $value;
    public $context;

    protected $rules = [
        'category' => 'required|string|max:50',
        'target' => 'required|string|max:50',
        'value' => 'required|string|max:255',
        'context' => 'nullable|string',
    ];

    public function openModal($id = null)
    {
        if ($id) {
            $memory = MemoryTag::findOrFail($id);
            $this->editingId = $id;
            $this->category = $memory->category;
            $this->target = $memory->target;
            $this->value = $memory->value;
            $this->context = $memory->context;
        } else {
            $this->resetForm();
        }
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save()
    {
        $this->validate();

        $persona = Auth::user()->persona;

        if (!$persona) {
            session()->flash('error', 'Please configure a persona first.');
            return;
        }

        $data = [
            'persona_id' => $persona->id,
            'category' => $this->category,
            'target' => $this->target,
            'value' => $this->value,
            'context' => $this->context,
        ];

        if ($this->editingId) {
            MemoryTag::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Memory updated successfully!');
        } else {
            MemoryTag::create($data);
            session()->flash('success', 'Memory added successfully!');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        MemoryTag::findOrFail($id)->delete();
        session()->flash('success', 'Memory deleted successfully!');
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->category = '';
        $this->target = '';
        $this->value = '';
        $this->context = '';
    }

    public function render()
    {
        $persona = Auth::user()->persona;
        $memories = $persona ? $persona->memoryTags()->latest()->get() : collect();

        return view('livewire.memory-brain', [
            'memories' => $memories,
        ])->layout('layouts.app');
    }
}
