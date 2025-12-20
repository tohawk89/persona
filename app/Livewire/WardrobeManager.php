<?php

namespace App\Livewire;

use App\Models\Persona;
use App\Models\WardrobeItem;
use App\Facades\Wardrobe;
use App\Services\WardrobeService;
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

    // AI Generation properties
    public $showGenerateModal = false;
    public $showReviewModal = false;
    public $generateSlot = null;
    public $generateCount = 5;
    public $selectedTags = [];
    public $customTags = [];
    public $newCustomTag = '';
    public $generatedOutfits = [];
    public $selectedForSave = [];
    public $primaryIndex = 0;
    public $isGenerating = false;
    public $editingGenerated = null;

    // Outfit modal tag management
    public $modalTags = [];
    public $modalCustomTags = [];
    public $newModalCustomTag = '';

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
        'generateCount' => 'required|integer|min:1|max:10',
        'selectedTags' => 'array',
        'customTags.*' => 'string|max:50',
        'modalTags' => 'array|max:10',
        'modalCustomTags.*' => 'string|max:50',
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
        $this->modalTags = [];
        $this->modalCustomTags = [];
        $this->newModalCustomTag = '';
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
        
        // Load tags
        $allTags = $item->tags ?? [];
        $this->modalTags = array_intersect($allTags, WardrobeService::PREDEFINED_TAGS);
        $this->modalCustomTags = array_diff($allTags, WardrobeService::PREDEFINED_TAGS);
        $this->newModalCustomTag = '';

        $this->showModal = true;
    }

    public function saveOutfit()
    {
        $this->validate();
        
        // Validate max 10 tags
        $allTags = array_merge($this->modalTags, $this->modalCustomTags);
        if (count($allTags) > 10) {
            session()->flash('error', 'Maximum 10 tags allowed per outfit.');
            return;
        }

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
            $item->update([
                ...$this->form,
                'tags' => $allTags,
            ]);
            session()->flash('message', 'Outfit updated successfully.');
        } else {
            // Create new
            WardrobeItem::create([
                'persona_id' => $this->persona->id,
                'slot_name' => $this->selectedSlot,
                ...$this->form,
                'tags' => $allTags,
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
        $this->modalTags = [];
        $this->modalCustomTags = [];
        $this->newModalCustomTag = '';
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

    // ========================================
    // OUTFIT MODAL TAG MANAGEMENT
    // ========================================

    public function toggleModalTag($tag)
    {
        if (in_array($tag, $this->modalTags)) {
            $this->modalTags = array_diff($this->modalTags, [$tag]);
        } else {
            // Check max 10 tags
            if (count($this->modalTags) + count($this->modalCustomTags) >= 10) {
                session()->flash('error', 'Maximum 10 tags allowed per outfit.');
                return;
            }
            $this->modalTags[] = $tag;
        }
        $this->modalTags = array_values($this->modalTags);
    }

    public function addModalCustomTag()
    {
        $tag = trim($this->newModalCustomTag);
        if (empty($tag)) {
            return;
        }

        // Check if already exists
        if (in_array($tag, $this->modalCustomTags) || in_array($tag, $this->modalTags)) {
            $this->newModalCustomTag = '';
            return;
        }

        // Check max 10 tags
        if (count($this->modalTags) + count($this->modalCustomTags) >= 10) {
            session()->flash('error', 'Maximum 10 tags allowed per outfit.');
            return;
        }

        $this->modalCustomTags[] = $tag;
        $this->newModalCustomTag = '';
    }

    public function removeModalCustomTag($tag)
    {
        $this->modalCustomTags = array_diff($this->modalCustomTags, [$tag]);
        $this->modalCustomTags = array_values($this->modalCustomTags);
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

    // ========================================
    // AI GENERATION METHODS
    // ========================================

    public function openGenerateModal($slot)
    {
        $this->generateSlot = $slot;
        $this->generateCount = 5;
        $this->selectedTags = [];
        $this->customTags = [];
        $this->newCustomTag = '';
        $this->showGenerateModal = true;
    }

    public function toggleTag($tag)
    {
        if (in_array($tag, $this->selectedTags)) {
            $this->selectedTags = array_diff($this->selectedTags, [$tag]);
        } else {
            $this->selectedTags[] = $tag;
        }
        $this->selectedTags = array_values($this->selectedTags);
    }

    public function addCustomTag()
    {
        $tag = trim($this->newCustomTag);
        if (!empty($tag) && !in_array($tag, $this->customTags) && !in_array($tag, $this->selectedTags)) {
            $this->customTags[] = $tag;
            $this->selectedTags[] = $tag;
            $this->newCustomTag = '';
        }
    }

    public function removeCustomTag($tag)
    {
        $this->customTags = array_diff($this->customTags, [$tag]);
        $this->selectedTags = array_diff($this->selectedTags, [$tag]);
        $this->customTags = array_values($this->customTags);
        $this->selectedTags = array_values($this->selectedTags);
    }

    public function generateWithAI()
    {
        $this->validate([
            'generateCount' => 'required|integer|min:1|max:10',
        ]);

        $this->isGenerating = true;

        try {
            $allTags = array_merge($this->selectedTags, $this->customTags);

            $this->generatedOutfits = Wardrobe::generateOutfits(
                $this->persona,
                $this->generateSlot,
                $allTags,
                $this->generateCount
            )->toArray();

            // Pre-select all for saving
            $this->selectedForSave = array_keys($this->generatedOutfits);
            $this->primaryIndex = 0;

            $this->showGenerateModal = false;
            $this->showReviewModal = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Generation failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to generate outfits. Please try again.');
        } finally {
            $this->isGenerating = false;
        }
    }

    public function generateSimilar($outfitId)
    {
        $this->isGenerating = true;

        try {
            $existingOutfit = WardrobeItem::findOrFail($outfitId);

            $this->generatedOutfits = Wardrobe::generateSimilarOutfits($existingOutfit, 3)->toArray();

            $this->generateSlot = $existingOutfit->slot_name;
            $this->selectedForSave = array_keys($this->generatedOutfits);
            $this->primaryIndex = -1; // None is primary by default

            $this->showReviewModal = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Generate similar failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to generate similar outfits.');
        } finally {
            $this->isGenerating = false;
        }
    }

    public function toggleOutfitForSave($index)
    {
        if (in_array($index, $this->selectedForSave)) {
            $this->selectedForSave = array_diff($this->selectedForSave, [$index]);
        } else {
            $this->selectedForSave[] = $index;
        }

        $this->selectedForSave = array_values($this->selectedForSave);
    }

    public function setPrimaryGenerated($index)
    {
        $this->primaryIndex = $index;
    }

    public function editGeneratedOutfit($index)
    {
        $this->editingGenerated = $index;
        $outfit = $this->generatedOutfits[$index];

        // Populate form with generated data
        $this->form = [
            'description' => $outfit['description'] ?? '',
            'upper_body' => $outfit['upper_body'] ?? '',
            'lower_body' => $outfit['lower_body'] ?? '',
            'footwear' => $outfit['footwear'] ?? '',
            'accessories' => $outfit['accessories'] ?? '',
            'is_primary' => false,
        ];
    }

    public function saveEditedGenerated()
    {
        if ($this->editingGenerated !== null) {
            $this->generatedOutfits[$this->editingGenerated] = [
                'description' => $this->form['description'],
                'upper_body' => $this->form['upper_body'],
                'lower_body' => $this->form['lower_body'],
                'footwear' => $this->form['footwear'],
                'accessories' => $this->form['accessories'],
                'tags' => $this->generatedOutfits[$this->editingGenerated]['tags'] ?? [],
            ];

            $this->editingGenerated = null;
            $this->resetForm();
        }
    }

    public function cancelEditGenerated()
    {
        $this->editingGenerated = null;
        $this->resetForm();
    }

    public function saveGeneratedOutfits()
    {
        if (empty($this->selectedForSave)) {
            session()->flash('error', 'Please select at least one outfit to save.');
            return;
        }

        $savedCount = 0;

        foreach ($this->selectedForSave as $index) {
            if (!isset($this->generatedOutfits[$index])) {
                continue;
            }

            $outfit = $this->generatedOutfits[$index];

            $isPrimary = ($index === $this->primaryIndex);

            // If setting as primary, unset other primaries in this slot
            if ($isPrimary) {
                WardrobeItem::where('persona_id', $this->persona->id)
                    ->where('slot_name', $this->generateSlot)
                    ->update(['is_primary' => false]);
            }

            WardrobeItem::create([
                'persona_id' => $this->persona->id,
                'slot_name' => $this->generateSlot,
                'is_primary' => $isPrimary,
                'description' => $outfit['description'],
                'upper_body' => $outfit['upper_body'],
                'lower_body' => $outfit['lower_body'],
                'footwear' => $outfit['footwear'],
                'accessories' => $outfit['accessories'],
                'tags' => $outfit['tags'] ?? [],
            ]);

            $savedCount++;
        }

        session()->flash('message', "{$savedCount} outfit(s) added successfully!");

        $this->closeGenerateModals();
    }

    public function closeGenerateModals()
    {
        $this->showGenerateModal = false;
        $this->showReviewModal = false;
        $this->generatedOutfits = [];
        $this->selectedForSave = [];
        $this->editingGenerated = null;
        $this->resetForm();
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
            'predefinedTags' => WardrobeService::PREDEFINED_TAGS,
        ])->layout('layouts.persona', ['persona' => $this->persona]);
    }
}
