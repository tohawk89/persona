<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\MemoryTag;
use Illuminate\Support\Facades\Auth;
use App\Facades\Brain;
use Illuminate\Support\Facades\Log;

class MemoryBrain extends Component
{
    public Persona $persona;
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

    public function mount(Persona $persona)
    {
        // Authorization: Ensure user owns this persona
        if ($persona->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to persona.');
        }

        $this->persona = $persona;
    }

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

        $persona = $this->persona;

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

    public function organizeMemoryTags()
    {
        try {
            $memories = $this->persona->memoryTags;

            if ($memories->count() === 0) {
                session()->flash('error', 'No memory tags to organize.');
                return;
            }

            // Process in batches of 10 to avoid AI token limits
            $batches = $memories->chunk(10);
            $totalKept = 0;
            $totalMerged = 0;
            $totalUpdated = 0;

            foreach ($batches as $batch) {
                $tagsList = $batch->map(fn($tag) => [
                    'id' => $tag->id,
                    'category' => $tag->category,
                    'target' => $tag->target,
                    'value' => $tag->value,
                ])->toArray();

                $tagsJson = json_encode($tagsList);

                $prompt = <<<PROMPT
 Analyze these memory tags and organize them.

TAGS: {$tagsJson}

RULES:
1. Merge duplicates (e.g., \"Korean\", \"nationality: korean\" → merge)
2. Importance scores: 10=identity, 8-9=traits, 5-7=facts, 3-4=minor, 1-2=trivial

Return JSON array with ALL tags:
[{\"action\": \"keep\", \"id\": 1, \"importance\": 10}]

Actions: keep, merge (needs merge_into_id), update (needs new_value)
PROMPT;

                $response = Brain::generate($prompt);

                // Clean response
                $response = trim($response);
                $response = preg_replace('/^```(json)?\s*/i', '', $response);
                $response = preg_replace('/\s*```$/i', '', $response);

                // Extract JSON
                if (preg_match('/\[.*\]/s', $response, $matches)) {
                    $response = $matches[0];
                }

                $instructions = json_decode($response, true);

                if (!is_array($instructions)) {
                    Log::warning('MemoryBrain: Batch parse failed', ['error' => json_last_error_msg()]);
                    continue;
                }

                foreach ($instructions as $inst) {
                    $tag = MemoryTag::find($inst['id'] ?? null);
                    if (!$tag) continue;

                    switch ($inst['action'] ?? 'keep') {
                        case 'keep':
                            $tag->update([
                                'importance' => $inst['importance'] ?? 5,
                                'last_consolidated_at' => now(),
                            ]);
                            $totalKept++;
                            break;

                        case 'merge':
                        case 'delete':
                            $tag->delete();
                            $totalMerged++;
                            break;

                        case 'update':
                            $tag->update([
                                'value' => $inst['new_value'] ?? $tag->value,
                                'importance' => $inst['importance'] ?? 5,
                                'last_consolidated_at' => now(),
                            ]);
                            $totalUpdated++;
                            break;
                    }
                }
            }

            session()->flash('success', "Tags organized! {$totalKept} kept, {$totalUpdated} updated, {$totalMerged} removed.");
        } catch (\Exception $e) {
            Log::error('MemoryBrain: Organization failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to organize tags.');
        }
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
        $memories = $this->persona->memoryTags()
            ->orderByRaw('importance IS NULL, importance DESC')
            ->latest()
            ->get();

        return view('livewire.memory-brain', [
            'memories' => $memories,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
