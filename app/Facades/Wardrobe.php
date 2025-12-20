<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\WardrobeItem|null getTodaysOutfit(\App\Models\Persona $persona, string $timeContext)
 * @method static \App\Models\WardrobeItem selectOutfitForDay(\App\Models\Persona $persona, string $slot, \Carbon\Carbon $date)
 * @method static string buildOutfitDescription(\App\Models\WardrobeItem $item, string $shotType)
 * @method static \App\Models\WardrobeItem setOutfit(int $personaId, string $slot, array $parts, bool $isPrimary = false)
 * @method static \Illuminate\Support\Collection getOutfitHistory(int $personaId, int $days = 7)
 * @method static string getCurrentTimeContext()
 *
 * @see \App\Services\WardrobeService
 */
class Wardrobe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\WardrobeService::class;
    }
}
