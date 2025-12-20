<?php

namespace App\Livewire;

use App\Models\Persona;
use App\Models\WardrobeItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WardrobeManager extends Component
{
    public Persona $persona;
    public $showModal = false;
    public $editingId = null;
    public $selectedSlot = 'casual_daytime';
    public $activeTab = 'wardrobe';
    public $historyDateRange = 30;
    public $outfitHistory = [];
    public $analytics = [];

    public $form = [
        'description' => '',
        'upper_body' => '',
        'lower_body' => '',
        'footwear' => '',
        'accessories' => '',
        'is_primary' => false,
    ];

    public $slots = [
        'casual_daytime' => ['icon' => '🌞', 'label' => 'Casual Daytime'],
        'casual_nighttime' => ['icon' => '🌙', 'label' => 'Casual Nighttime'],
        'formal' => ['icon' => '👔', 'label' => 'Formal'],
        'workout' => ['icon' => '💪', 'label' => 'Workout'],
        'sleepwear' => ['icon' => '😴', 'label' => 'Sleepwear'],
    ];

    protected $rules = [
        'form.description' => 'required|string|min:10',
        'form.upper_body' => 'nullable|string',
        'form.lower_body' => 'nullable|string',
        'form.footwear' => 'nullable|string',
        'form.accessories' => 'nullable|string',
        'form.is_primary' => 'boolean',
    ];

    public function mount(Persona $persona)
    {
        $this->persona = $persona;
        $this->loadHistory();
        $this->loadAnalytics();
    }

    public function openAddModal($slot)
    {
        $this->resetForm();
        $this->selectedSlot = $slot;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $item = WardrobeItem::findOrFail($id);

        $this->editingId = $id;
        $this->selectedSlot = $item->slot_name;
        $this->form = [
            'description' => $item->description,
            'upper_body' => $item->upper_body ?? '',
            'lower_body' => $item->lower_body ?? '',
            'footwear' => $item->footwear ?? '',
            'accessories' => $item->accessories ?? '',
            'is_primary' => $item->is_primary,
        ];

        $this->showModal = true;
    }

    public function saveOutfit()
    {
        $this->validate();

        // If setting as primary, unset other primaries in this slot
        if ($this->form['is_primary']) {
            WardrobeItem::where('persona_id', $this->persona->id)
                ->where('slot_name', $this->selectedSlot)
                ->where('id', '!=', $this->editingId)
                ->update(['is_primary' => false]);
        }

        if ($this->editingId) {
            // Update existing
            $item = WardrobeItem::findOrFail($this->editingId);
            $item->update($this->form);
            session()->flash('message', 'Outfit updated successfully.');
        } else {
            // Create new
            WardrobeItem::create([
                'persona_id' => $this->persona->id,
                'slot_name' => $this->selectedSlot,
                ...$this->form,
            ]);
            session()->flash('message', 'Outfit added successfully.');
        }

        $this->closeModal();
    }

    public function deleteOutfit($id)
    {
        $item = WardrobeItem::findOrFail($id);

        if ($item->is_primary) {
            session()->flash('error', 'Cannot delete primary outfit. Set another outfit as primary first.');
            return;
        }

        $item->delete();
        session()->flash('message', 'Outfit deleted successfully.');
    }

    public function setPrimary($id)
    {
        $item = WardrobeItem::findOrFail($id);

        // Unset other primaries in this slot
        WardrobeItem::where('persona_id', $this->persona->id)
            ->where('slot_name', $item->slot_name)
            ->update(['is_primary' => false]);

        // Set this as primary
        $item->update(['is_primary' => true]);

        session()->flash('message', 'Primary outfit updated.');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->editingId = null;
    }

    private function resetForm()
    {
        $this->form = [
            'description' => '',
            'upper_body' => '',
            'lower_body' => '',
            'footwear' => '',
            'accessories' => '',
            'is_primary' => false,
        ];
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;

        if ($tab === 'history') {
            $this->loadHistory();
        } elseif ($tab === 'analytics') {
            $this->loadAnalytics();
        }
    }

    public function updateDateRange($days)
    {
        $this->historyDateRange = $days;
        $this->loadHistory();
    }

    public function loadHistory()
    {
        $startDate = now()->subDays($this->historyDateRange);

        $this->outfitHistory = \App\Models\DailyOutfitSelection::where('persona_id', $this->persona->id)
            ->where('date', '>=', $startDate)
            ->with('wardrobeItem')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('date')
            ->map(function ($selections) {
                return $selections->keyBy('slot_name');
            });
    }

    public function loadAnalytics()
    {
        // Most worn outfits
        $mostWorn = WardrobeItem::where('persona_id', $this->persona->id)
            ->orderBy('wear_count', 'desc')
            ->limit(5)
            ->get();

        // Least worn outfits (excluding unworn)
        $leastWorn = WardrobeItem::where('persona_id', $this->persona->id)
            ->where('wear_count', '>', 0)
            ->orderBy('wear_count', 'asc')
            ->limit(5)
            ->get();

        // Unworn outfits
        $unworn = WardrobeItem::where('persona_id', $this->persona->id)
            ->where('wear_count', 0)
            ->get();

        // Primary vs non-primary usage
        $primaryWears = WardrobeItem::where('persona_id', $this->persona->id)
            ->where('is_primary', true)
            ->sum('wear_count');

        $nonPrimaryWears = WardrobeItem::where('persona_id', $this->persona->id)
            ->where('is_primary', false)
            ->sum('wear_count');

        $totalWears = $primaryWears + $nonPrimaryWears;
        $rotationEffectiveness = $totalWears > 0
            ? round(($nonPrimaryWears / $totalWears) * 100, 1)
            : 0;

        $this->analytics = [
            'most_worn' => $mostWorn,
            'least_worn' => $leastWorn,
            'unworn' => $unworn,
            'primary_wears' => $primaryWears,
            'non_primary_wears' => $nonPrimaryWears,
            'rotation_effectiveness' => $rotationEffectiveness,
            'total_outfits' => WardrobeItem::where('persona_id', $this->persona->id)->count(),
        ];
    }

    public function exportHistory()
    {
        $history = \App\Models\DailyOutfitSelection::where('persona_id', $this->persona->id)
            ->with('wardrobeItem')
            ->orderBy('date', 'desc')
            ->get();

        $csv = "Date,Slot,Outfit Description,Wear Count\n";

        foreach ($history as $selection) {
            $csv .= sprintf(
                "%s,%s,\"%s\",%d\n",
                $selection->date->format('Y-m-d'),
                str_replace('_', ' ', ucwords($selection->slot_name, '_')),
                str_replace('"', '""', $selection->wardrobeItem->description ?? 'N/A'),
                $selection->wardrobeItem->wear_count ?? 0
            );
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'outfit-history-' . $this->persona->name . '-' . now()->format('Y-m-d') . '.csv');
    }

    public function render()
    {
        $wardrobeBySlot = [];

        foreach ($this->slots as $slotName => $slotInfo) {
            $wardrobeBySlot[$slotName] = WardrobeItem::where('persona_id', $this->persona->id)
                ->where('slot_name', $slotName)
                ->orderBy('is_primary', 'desc')
                ->orderBy('wear_count', 'desc')
                ->get();
        }

        return view('livewire.wardrobe-manager', [
            'wardrobeBySlot' => $wardrobeBySlot,
            'persona' => $this->persona,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
