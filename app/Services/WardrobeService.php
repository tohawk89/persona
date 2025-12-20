<?php

namespace App\Services;

use App\Models\DailyOutfitSelection;
use App\Models\Persona;
use App\Models\WardrobeItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WardrobeService
{
    /**
     * Get today's outfit for a persona based on time context.
     *
     * @param Persona $persona
     * @param string $timeContext 'daytime' or 'nighttime'
     * @return WardrobeItem|null
     */
    public function getTodaysOutfit(Persona $persona, string $timeContext): ?WardrobeItem
    {
        // Map time context to slot name
        $slotName = match ($timeContext) {
            'daytime' => 'casual_daytime',
            'nighttime' => 'casual_nighttime',
            default => 'casual_daytime',
        };

        $today = Carbon::today();

        try {
            return $this->selectOutfitForDay($persona, $slotName, $today);
        } catch (\Exception $e) {
            Log::warning('WardrobeService: No outfit found', [
                'persona_id' => $persona->id,
                'slot_name' => $slotName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Select outfit for a specific day with 70/30 rotation logic.
     *
     * 70% chance: Primary outfit
     * 30% chance: Random from rotation pool
     * Never repeats yesterday's outfit
     *
     * @param Persona $persona
     * @param string $slot
     * @param Carbon $date
     * @return WardrobeItem
     * @throws \Exception
     */
    public function selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem
    {
        // Check if already selected today (cached)
        $cached = DailyOutfitSelection::where('persona_id', $persona->id)
            ->where('date', $date->toDateString())
            ->where('slot_name', $slot)
            ->first();

        if ($cached) {
            Log::debug('WardrobeService: Using cached outfit', [
                'persona_id' => $persona->id,
                'slot' => $slot,
                'date' => $date->toDateString(),
                'outfit_id' => $cached->wardrobe_item_id,
            ]);
            return $cached->wardrobeItem;
        }

        // Get yesterday's outfit to avoid repeat
        $yesterday = DailyOutfitSelection::where('persona_id', $persona->id)
            ->where('date', $date->copy()->subDay()->toDateString())
            ->where('slot_name', $slot)
            ->first()?->wardrobe_item_id;

        // Get available outfits (excluding yesterday's if exists)
        $query = WardrobeItem::where('persona_id', $persona->id)
            ->where('slot_name', $slot);

        if ($yesterday) {
            $query->where('id', '!=', $yesterday);
        }

        $outfits = $query->get();

        if ($outfits->isEmpty()) {
            throw new \Exception("No outfits found for persona {$persona->id}, slot: {$slot}");
        }

        // If only one outfit, return it
        if ($outfits->count() === 1) {
            $selected = $outfits->first();
        } else {
            // 70% chance: use primary, 30% random
            $primary = $outfits->where('is_primary', true)->first();

            if (rand(1, 100) <= 70 && $primary) {
                $selected = $primary;
            } else {
                $selected = $outfits->random();
            }
        }

        // Cache selection
        DailyOutfitSelection::create([
            'persona_id' => $persona->id,
            'date' => $date->toDateString(),
            'slot_name' => $slot,
            'wardrobe_item_id' => $selected->id,
        ]);

        // Update wear stats
        $selected->update([
            'last_worn_at' => now(),
            'wear_count' => $selected->wear_count + 1,
        ]);

        Log::info('WardrobeService: Outfit selected', [
            'persona_id' => $persona->id,
            'slot' => $slot,
            'outfit_id' => $selected->id,
            'description' => $selected->description,
            'is_primary' => $selected->is_primary,
        ]);

        return $selected;
    }

    /**
     * Build outfit description filtered by shot type.
     *
     * @param WardrobeItem $item
     * @param string $shotType (e.g., 'close-up portrait', 'medium shot', 'full body')
     * @return string
     */
    public function buildOutfitDescription(WardrobeItem $item, string $shotType): string
    {
        $shotTypeLower = strtolower($shotType);

        // Close-up portrait: upper body only
        if (str_contains($shotTypeLower, 'close-up') || str_contains($shotTypeLower, 'portrait')) {
            $parts = array_filter([
                $item->upper_body,
                $item->accessories,
            ]);
            return implode(' with ', $parts) ?: $item->description;
        }

        // Medium shot: upper + accessories (no footwear)
        if (str_contains($shotTypeLower, 'medium')) {
            $parts = array_filter([
                $item->upper_body,
                $item->lower_body,
                $item->accessories,
            ]);
            return implode(' with ', $parts) ?: $item->description;
        }

        // Full body: everything
        if (str_contains($shotTypeLower, 'full body') || str_contains($shotTypeLower, 'outfit check')) {
            $parts = array_filter([
                $item->upper_body,
                $item->lower_body,
                $item->footwear,
                $item->accessories,
            ]);
            return implode(' with ', $parts) ?: $item->description;
        }

        // Default: use full description
        return $item->description;
    }

    /**
     * Create or update an outfit in the wardrobe.
     *
     * @param int $personaId
     * @param string $slot
     * @param array $parts ['description', 'upper_body', 'lower_body', 'footwear', 'accessories']
     * @param bool $isPrimary
     * @return WardrobeItem
     */
    public function setOutfit(int $personaId, string $slot, array $parts, bool $isPrimary = false): WardrobeItem
    {
        // If setting as primary, unset other primaries in this slot
        if ($isPrimary) {
            WardrobeItem::where('persona_id', $personaId)
                ->where('slot_name', $slot)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $outfit = WardrobeItem::create([
            'persona_id' => $personaId,
            'slot_name' => $slot,
            'description' => $parts['description'] ?? '',
            'upper_body' => $parts['upper_body'] ?? null,
            'lower_body' => $parts['lower_body'] ?? null,
            'footwear' => $parts['footwear'] ?? null,
            'accessories' => $parts['accessories'] ?? null,
            'is_primary' => $isPrimary,
        ]);

        Log::info('WardrobeService: Outfit created', [
            'persona_id' => $personaId,
            'slot' => $slot,
            'outfit_id' => $outfit->id,
            'is_primary' => $isPrimary,
        ]);

        return $outfit;
    }

    /**
     * Get outfit history for a persona.
     *
     * @param int $personaId
     * @param int $days Number of days to look back
     * @return \Illuminate\Support\Collection
     */
    public function getOutfitHistory(int $personaId, int $days = 7)
    {
        return DailyOutfitSelection::where('persona_id', $personaId)
            ->where('date', '>=', Carbon::today()->subDays($days))
            ->with('wardrobeItem')
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Determine time context from current hour.
     *
     * @return string 'daytime' or 'nighttime'
     */
    public function getCurrentTimeContext(): string
    {
        $hour = Carbon::now()->hour;

        // 6am-9pm = daytime, 9pm-6am = nighttime
        return ($hour >= 6 && $hour < 21) ? 'daytime' : 'nighttime';
    }
}
