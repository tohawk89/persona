<?php

namespace App\Services;

use App\Models\DailyOutfitSelection;
use App\Models\Persona;
use App\Models\WardrobeItem;
use App\Models\WardrobeGenerationLog;
use Carbon\Carbon;
use Gemini;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WardrobeService
{
    // ========================================
    // CONSTANTS
    // ========================================

    const PREDEFINED_TAGS = [
        'cute',
        'elegant',
        'modest',
        'hijab',
        'formal',
        'casual',
        'sporty',
        'trendy',
        'professional',
        'comfortable',
        'vintage',
        'bohemian',
        'minimalist',
        'colorful',
    ];

    // ========================================
    // PUBLIC API METHODS
    // ========================================

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

        // EMERGENCY FALLBACK: Auto-generate outfit if none exist
        if ($outfits->isEmpty()) {
            Log::info('WardrobeService: No outfits found, auto-generating fallback', [
                'persona_id' => $persona->id,
                'slot' => $slot,
            ]);

            try {
                // Generate one outfit with default persona style
                $generated = $this->generateOutfits(
                    $persona,
                    $slot,
                    $this->getDefaultTags($persona),
                    1 // Just one outfit
                )->first();

                if (!$generated) {
                    throw new \Exception("Failed to generate fallback outfit");
                }

                // Auto-save as primary
                $outfit = WardrobeItem::create([
                    'persona_id' => $persona->id,
                    'slot_name' => $slot,
                    'is_primary' => true,
                    'description' => $generated['description'],
                    'upper_body' => $generated['upper_body'],
                    'lower_body' => $generated['lower_body'],
                    'footwear' => $generated['footwear'],
                    'accessories' => $generated['accessories'],
                    'tags' => $generated['tags'] ?? [],
                ]);

                Log::info('WardrobeService: Auto-generated fallback outfit', [
                    'persona_id' => $persona->id,
                    'slot' => $slot,
                    'outfit_id' => $outfit->id,
                ]);

                // Cache the selection
                DailyOutfitSelection::create([
                    'persona_id' => $persona->id,
                    'date' => $date->toDateString(),
                    'slot_name' => $slot,
                    'wardrobe_item_id' => $outfit->id,
                ]);

                return $outfit;
            } catch (\Exception $e) {
                Log::error('WardrobeService: Fallback generation failed', [
                    'persona_id' => $persona->id,
                    'slot' => $slot,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception("No outfits found and fallback generation failed for persona {$persona->id}, slot: {$slot}");
            }
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

    // ========================================
    // AI GENERATION METHODS
    // ========================================

    /**
     * Generate outfits using Gemini AI.
     *
     * @param Persona $persona
     * @param string $slot Slot name (casual_daytime, casual_nighttime, etc.)
     * @param array $tags Style tags to apply
     * @param int $count Number of outfits to generate (1-10)
     * @param string|null $referenceDescription Existing outfit to use as style reference
     * @return Collection Collection of outfit data arrays
     * @throws \Exception
     */
    public function generateOutfits(
        Persona $persona,
        string $slot,
        array $tags = [],
        int $count = 5,
        ?string $referenceDescription = null
    ): Collection {
        // Check rate limit
        if (!$this->checkGenerationLimit($persona)) {
            throw new \Exception('Generation limit exceeded. Please try again tomorrow.');
        }

        // Validate count
        if ($count < 1 || $count > 10) {
            throw new \Exception('Count must be between 1 and 10');
        }

        // Build prompt
        $prompt = $this->buildGenerationPrompt($persona, $slot, $tags, $count, $referenceDescription);

        try {
            // Call Gemini
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            $result = $client->generativeModel(config('services.gemini.model'))
                ->withGenerationConfig(
                    new \Gemini\Data\GenerationConfig(
                        temperature: 0.9,
                        responseMimeType: ResponseMimeType::APPLICATION_JSON
                    )
                )
                ->generateContent($prompt);

            $outfits = json_decode($result->text(), true);

            if (!is_array($outfits)) {
                throw new \Exception('Invalid response format from Gemini');
            }

            // Validate and clean
            $validated = collect($outfits)->map(function ($outfit) use ($tags) {
                return [
                    'description' => $outfit['description'] ?? '',
                    'upper_body' => $outfit['upper_body'] ?? null,
                    'lower_body' => $outfit['lower_body'] ?? null,
                    'footwear' => $outfit['footwear'] ?? null,
                    'accessories' => $outfit['accessories'] ?? null,
                    'tags' => $outfit['tags'] ?? $tags,
                ];
            })->filter(fn($o) => !empty($o['description']));

            // Log generation
            $this->logGeneration($persona, $slot, $tags, $validated->count());

            Log::info('WardrobeService: Generated outfits', [
                'persona_id' => $persona->id,
                'slot' => $slot,
                'tags' => $tags,
                'count' => $validated->count(),
            ]);

            return $validated;
        } catch (\Exception $e) {
            Log::error('WardrobeService: Generation failed', [
                'persona_id' => $persona->id,
                'slot' => $slot,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to generate outfits: ' . $e->getMessage());
        }
    }

    /**
     * Generate similar outfit variations from an existing outfit.
     *
     * @param WardrobeItem $existing
     * @param int $count Number of variations (default 3)
     * @return Collection
     * @throws \Exception
     */
    public function generateSimilarOutfits(WardrobeItem $existing, int $count = 3): Collection
    {
        return $this->generateOutfits(
            $existing->persona,
            $existing->slot_name,
            $existing->tags ?? [],
            $count,
            $existing->description
        );
    }

    /**
     * Extract default style tags from persona's system prompt.
     *
     * @param Persona $persona
     * @return array
     */
    public function getDefaultTags(Persona $persona): array
    {
        $prompt = strtolower($persona->system_prompt ?? '');
        $defaultTags = [];

        // Simple keyword matching
        $tagMappings = [
            'modest' => ['modest', 'hijab', 'covered'],
            'hijab' => ['hijab', 'tudung', 'headscarf'],
            'cute' => ['cute', 'kawaii', 'adorable', 'sweet'],
            'elegant' => ['elegant', 'sophisticated', 'classy', 'graceful'],
            'casual' => ['casual', 'relaxed', 'comfortable'],
            'formal' => ['formal', 'professional', 'business'],
            'sporty' => ['sporty', 'athletic', 'active'],
            'trendy' => ['trendy', 'fashionable', 'stylish'],
        ];

        foreach ($tagMappings as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($prompt, $keyword)) {
                    $defaultTags[] = $tag;
                    break;
                }
            }
        }

        // Fallback to safe defaults
        return !empty($defaultTags) ? array_unique($defaultTags) : ['casual', 'comfortable'];
    }

    /**
     * Check if persona has exceeded generation limit.
     *
     * @param Persona $persona
     * @return bool True if allowed to generate
     */
    public function checkGenerationLimit(Persona $persona): bool
    {
        $today = Carbon::today();
        $count = WardrobeGenerationLog::where('persona_id', $persona->id)
            ->whereDate('generated_at', '>=', $today)
            ->sum('outfits_generated');

        // Set high limit for now (100 outfits per day)
        // Can be reduced later via config
        $limit = config('wardrobe.generation_daily_limit', 100);

        if ($count >= $limit) {
            Log::warning('WardrobeService: Generation limit reached', [
                'persona_id' => $persona->id,
                'count' => $count,
                'limit' => $limit,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Log outfit generation event.
     *
     * @param Persona $persona
     * @param string $slot
     * @param array $tags
     * @param int $count
     * @return void
     */
    private function logGeneration(Persona $persona, string $slot, array $tags, int $count): void
    {
        WardrobeGenerationLog::create([
            'persona_id' => $persona->id,
            'slot_name' => $slot,
            'tags_used' => $tags,
            'outfits_generated' => $count,
        ]);
    }

    /**
     * Build AI prompt for outfit generation.
     *
     * @param Persona $persona
     * @param string $slot
     * @param array $tags
     * @param int $count
     * @param string|null $referenceDescription
     * @return string
     */
    private function buildGenerationPrompt(
        Persona $persona,
        string $slot,
        array $tags,
        int $count,
        ?string $referenceDescription = null
    ): string {
        // Map slot to context
        $timeContext = match ($slot) {
            'casual_daytime' => 'daytime casual wear (6am-9pm)',
            'casual_nighttime' => 'nighttime/sleepwear (9pm-6am)',
            'formal' => 'formal events',
            'workout' => 'exercise and sports',
            'sleepwear' => 'comfortable sleep attire',
            default => 'casual everyday wear',
        };

        $styleHints = !empty($tags) ? implode(', ', $tags) : 'comfortable, casual';
        $reference = $referenceDescription
            ? "\n\nREFERENCE OUTFIT (create distinct variations of this style):\n{$referenceDescription}"
            : '';

        return <<<PROMPT
You are a fashion designer creating outfits for {$persona->name}.

PERSONA CONTEXT:
- Physical traits: {$persona->physical_traits}
- Gender: {$persona->gender}
- Style preferences: {$styleHints}
{$reference}

TASK: Generate {$count} DISTINCT outfit ideas for {$timeContext} context.

REQUIREMENTS:
1. Match the selected style tags: {$styleHints}
2. Each outfit must be visually distinct (different colors, styles, patterns)
3. Respect cultural/religious preferences (e.g., modest/hijab if tagged)
4. Be specific about colors, fabrics, and styles
5. Practical and wearable combinations
6. For dresses/jumpsuits, set lower_body to null

OUTPUT FORMAT (JSON array):
[
  {
    "description": "Full outfit description as one clear sentence",
    "upper_body": "Specific top/dress/blouse",
    "lower_body": "Specific bottom OR null for dresses",
    "footwear": "Specific shoes/sandals",
    "accessories": "Optional accessories OR null",
    "tags": ["tag1", "tag2"]
  }
]

IMPORTANT: Return ONLY the JSON array, no additional text.

Generate {$count} distinct outfits now:
PROMPT;
    }
}
