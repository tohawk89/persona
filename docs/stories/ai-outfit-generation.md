# Epic: AI-Powered Outfit Generation with Tags

**Epic ID:** PERSONA-WRD-002  
**Priority:** High  
**Estimated Total:** 21 Story Points  
**Target Sprint:** Sprint 2025-W51  
**Status:** Ready for Development  
**Dependencies:** PERSONA-WRD-001 (Wardrobe System)

---

## Epic Overview

### Business Goal
Implement AI-powered bulk outfit generation with tagging system to dramatically reduce manual wardrobe setup time and provide automatic fallback when no outfits exist, preventing image generation failures.

### Success Metrics
- ✅ Reduce wardrobe setup time from 10+ minutes to 30 seconds per slot
- ✅ Zero image generation failures due to missing outfits (emergency fallback)
- ✅ 80%+ user satisfaction with generated outfit quality
- ✅ Average 5+ outfits per slot (increased variety)

### User Value
**Primary:** "As a user, I want to quickly generate multiple themed outfits with AI instead of manually creating them one by one, so I can set up a complete wardrobe in minutes."

**Secondary:** "As a user, I want the system to automatically generate outfits when needed for image generation, so I never encounter errors due to empty wardrobes."

---

## Problem Statement

### Current Pain Points
1. **Manual Entry is Tedious:** Users must manually type each outfit description, parts breakdown
2. **Time Consuming:** Setting up 5 outfits per slot × 5 slots = 25 manual entries
3. **Writer's Block:** Users struggle to come up with varied outfit ideas
4. **Image Generation Failures:** If wardrobe is empty, image generation throws error
5. **No Style Organization:** Hard to find specific style outfits (e.g., all "elegant" outfits)

### Solution Overview
- **Tag System:** Categorize outfits by style (cute, elegant, hijab, etc.)
- **Bulk Generation:** AI creates 1-10 outfits at once with selected tags
- **Generate Similar:** Copy existing outfit's style to create variations
- **Emergency Fallback:** Auto-generate outfit if slot is empty during image generation
- **Future-Ready:** Rate limiting infrastructure for production scaling

---

## Architecture Summary

### Database Schema Changes

```sql
-- Add tags to existing wardrobe_items table
ALTER TABLE wardrobe_items 
ADD COLUMN tags JSON NULL AFTER accessories;

-- New table for generation tracking (rate limiting future)
CREATE TABLE wardrobe_generation_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    persona_id BIGINT UNSIGNED NOT NULL,
    slot_name VARCHAR(50) NOT NULL,
    tags_used JSON NULL COMMENT 'Tags selected during generation',
    outfits_generated INT NOT NULL COMMENT 'Number of outfits created',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    INDEX idx_rate_limit (persona_id, generated_at)
);
```

### Service Layer Extensions

**WardrobeService New Methods:**
- `generateOutfits(Persona $persona, string $slot, array $tags, int $count): Collection`
- `generateSimilarOutfits(WardrobeItem $existing, int $count): Collection`
- `getDefaultTags(Persona $persona): array`
- `checkGenerationLimit(Persona $persona): bool`
- `logGeneration(Persona $persona, string $slot, array $tags, int $count): void`

**Updated Method:**
- `selectOutfitForDay()` - Add emergency fallback generation when no outfits exist

### Integration Points
1. **Gemini 2.0 Flash** - AI generation with JSON output
2. **WardrobeService** - Extended with generation capabilities
3. **WardrobeManager Livewire** - New modals and flows
4. **Image Generation** - Seamless fallback on empty wardrobe

---

## Story Breakdown

### Story 1: Tag System + Generation Logging (3 points)
**Story ID:** PERSONA-WRD-002-S1  
**Priority:** P0 (Blocker)

#### Description
Add tags JSON column to wardrobe_items and create generation logging table for future rate limiting.

#### Acceptance Criteria
- [ ] AC1: Migration adds `tags` JSON column to `wardrobe_items` table
- [ ] AC2: `WardrobeItem` model updated with `'tags' => 'array'` cast
- [ ] AC3: Migration creates `wardrobe_generation_log` table
- [ ] AC4: `WardrobeGenerationLog` model created with relationships
- [ ] AC5: Predefined tags constant defined in service:
  ```php
  const PREDEFINED_TAGS = [
      'cute', 'elegant', 'modest', 'hijab',
      'formal', 'casual', 'sporty', 'trendy',
      'professional', 'comfortable', 'vintage',
      'bohemian', 'minimalist', 'colorful'
  ];
  ```
- [ ] AC6: Tags are fillable and validated (max 10 tags per outfit)
- [ ] AC7: Migration runs cleanly without errors

#### Technical Implementation

**Migration File:**
```php
public function up(): void
{
    Schema::table('wardrobe_items', function (Blueprint $table) {
        $table->json('tags')->nullable()->after('accessories');
    });

    Schema::create('wardrobe_generation_log', function (Blueprint $table) {
        $table->id();
        $table->foreignId('persona_id')->constrained()->onDelete('cascade');
        $table->string('slot_name', 50);
        $table->json('tags_used')->nullable();
        $table->integer('outfits_generated');
        $table->timestamp('generated_at')->useCurrent();
        
        $table->index(['persona_id', 'generated_at']);
    });
}
```

#### Definition of Done
- Migration file created and run successfully
- WardrobeItem model accepts tags as array
- WardrobeGenerationLog model with persona relationship
- Can create outfit with tags: `WardrobeItem::create([..., 'tags' => ['cute', 'modest']])`
- Tags display in tinker: `$outfit->tags` returns array

---

### Story 2: AI Generation Service (5 points)
**Story ID:** PERSONA-WRD-002-S2  
**Priority:** P0  
**Dependencies:** Story 1

#### Description
Implement AI-powered outfit generation using Gemini 2.0 Flash with persona context, tag filtering, and quality validation.

#### Acceptance Criteria
- [ ] AC1: `generateOutfits()` method creates 1-10 outfits in one call
- [ ] AC2: Uses Gemini 2.0 Flash with JSON response format
- [ ] AC3: Generation includes:
  - Persona physical traits and style
  - Selected tags (cute, elegant, etc.)
  - Slot context (daytime/nighttime)
  - Variety enforcement (no duplicate colors/styles)
- [ ] AC4: Returns collection of outfit data (not saved yet):
  ```php
  [
      [
          'description' => 'Pink cardigan...',
          'upper_body' => 'Pink cardigan',
          'lower_body' => 'Blue jeans',
          'footwear' => 'White sneakers',
          'accessories' => null,
          'tags' => ['cute', 'casual']
      ],
      ...
  ]
  ```
- [ ] AC5: `generateSimilarOutfits()` takes existing outfit as reference
- [ ] AC6: `getDefaultTags()` extracts persona style from system_prompt or memory tags
- [ ] AC7: `logGeneration()` records to `wardrobe_generation_log` table
- [ ] AC8: `checkGenerationLimit()` checks rate (returns true for now, max 100/day prepared)
- [ ] AC9: Generation takes 5-10 seconds max per batch

#### Technical Implementation

**Gemini Prompt Structure:**
```php
private function buildGenerationPrompt(
    Persona $persona, 
    string $slot, 
    array $tags, 
    int $count,
    ?string $referenceDescription = null
): string {
    $timeContext = match($slot) {
        'casual_daytime' => 'daytime casual wear (6am-9pm)',
        'casual_nighttime' => 'nighttime/sleepwear (9pm-6am)',
        'formal' => 'formal events',
        'workout' => 'exercise and sports',
        'sleepwear' => 'comfortable sleep attire',
    };
    
    $styleHints = implode(', ', $tags);
    $reference = $referenceDescription 
        ? "Use this as inspiration but create distinct variations: {$referenceDescription}"
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

OUTPUT FORMAT (JSON array):
[
  {
    "description": "Full outfit description as one sentence",
    "upper_body": "Specific top/dress",
    "lower_body": "Specific bottom (null for dresses)",
    "footwear": "Specific shoes",
    "accessories": "Optional accessories (null if none)",
    "tags": ["tag1", "tag2"]
  }
]

Generate now:
PROMPT;
}
```

**Service Methods:**
```php
public function generateOutfits(
    Persona $persona,
    string $slot,
    array $tags = [],
    int $count = 5,
    ?string $referenceDescription = null
): Collection {
    // Check rate limit
    if (!$this->checkGenerationLimit($persona)) {
        throw new \Exception('Generation limit exceeded');
    }
    
    // Build prompt
    $prompt = $this->buildGenerationPrompt($persona, $slot, $tags, $count, $referenceDescription);
    
    // Call Gemini
    $client = Gemini::client(config('services.gemini.api_key'));
    $response = $client->geminiProFlash()->generateContent([
        'contents' => $prompt,
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature' => 0.9,
        ]
    ]);
    
    $outfits = json_decode($response->text(), true);
    
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
}

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

private function getDefaultTags(Persona $persona): array
{
    // Extract from system prompt or memory tags
    $prompt = strtolower($persona->system_prompt ?? '');
    $defaultTags = [];
    
    // Simple keyword matching
    if (str_contains($prompt, 'modest') || str_contains($prompt, 'hijab')) {
        $defaultTags[] = 'modest';
    }
    if (str_contains($prompt, 'cute') || str_contains($prompt, 'kawaii')) {
        $defaultTags[] = 'cute';
    }
    if (str_contains($prompt, 'elegant') || str_contains($prompt, 'sophisticated')) {
        $defaultTags[] = 'elegant';
    }
    
    return !empty($defaultTags) ? $defaultTags : ['casual', 'comfortable'];
}

public function checkGenerationLimit(Persona $persona): bool
{
    // Soft check for now - return true but prepare infrastructure
    $today = now()->startOfDay();
    $count = WardrobeGenerationLog::where('persona_id', $persona->id)
        ->where('generated_at', '>=', $today)
        ->sum('outfits_generated');
    
    // Set high limit for now (100 outfits per day)
    $limit = 100;
    
    if ($count >= $limit) {
        Log::warning('Generation limit reached', [
            'persona_id' => $persona->id,
            'count' => $count,
            'limit' => $limit,
        ]);
        return false;
    }
    
    return true;
}

private function logGeneration(Persona $persona, string $slot, array $tags, int $count): void
{
    WardrobeGenerationLog::create([
        'persona_id' => $persona->id,
        'slot_name' => $slot,
        'tags_used' => $tags,
        'outfits_generated' => $count,
    ]);
}
```

#### Testing Validation
```php
php artisan tinker
$persona = Persona::first();
$outfits = Wardrobe::generateOutfits($persona, 'casual_daytime', ['cute', 'modest'], 3);
// Should return collection of 3 outfits
// Check generation log: WardrobeGenerationLog::latest()->first()
```

#### Definition of Done
- Service methods implemented and working
- Gemini integration tested
- Returns valid outfit data structure
- Generation logged to database
- Rate limit check functional (soft limit)
- Manual testing passes with 3+ different tag combinations

---

### Story 3: Bulk Generation UI (5 points)
**Story ID:** PERSONA-WRD-002-S3  
**Priority:** P0  
**Dependencies:** Story 2

#### Description
Add "Generate with AI" button to wardrobe manager with modal for tag selection, count input, and review screen for bulk saving generated outfits.

#### Acceptance Criteria
- [ ] AC1: "✨ Generate with AI" button added to each slot card
- [ ] AC2: Generate modal shows:
  - Count selector (1-10)
  - Tag multi-select with predefined tags
  - Custom tag input
  - Loading state during generation
- [ ] AC3: Review modal displays generated outfits with:
  - Checkbox to include/exclude each outfit
  - Edit button for individual outfits
  - Primary outfit selector (radio buttons)
  - "Save All" button (saves only checked items)
- [ ] AC4: Loading spinner during generation (5-10 seconds)
- [ ] AC5: Error handling: Show friendly message if generation fails
- [ ] AC6: Success message after saving: "{count} outfits added to {slot}"
- [ ] AC7: Generated outfits immediately visible in slot card

#### UI Design Specs

**Generate Modal:**
```
┌─────────────────────────────────────────────┐
│ ✨ Generate Outfits with AI                 │
├─────────────────────────────────────────────┤
│ How many outfits? [5 ▼] (1-10)             │
│                                             │
│ Select Style Tags:                          │
│ ☑ Cute      ☐ Elegant    ☑ Modest         │
│ ☐ Hijab     ☐ Formal     ☐ Casual         │
│ ☐ Sporty    ☐ Trendy     ☐ Professional   │
│ ☐ Comfortable ☐ Vintage  ☐ Bohemian       │
│                                             │
│ Custom Tags: [+ Add]                        │
│ [minimalist] [x]                            │
│                                             │
│ [Cancel] [Generate →]                       │
└─────────────────────────────────────────────┘

After generation →

┌─────────────────────────────────────────────┐
│ Review Generated Outfits (5)                │
├─────────────────────────────────────────────┤
│ ☑ ⭐ Pink cardigan with white blouse,      │
│      blue jeans and sneakers                │
│      [Edit] Tags: cute, casual              │
│                                             │
│ ☑ ○ Floral midi dress with ballet flats    │
│      [Edit] Tags: cute, elegant             │
│                                             │
│ ☐ ○ Navy blazer... (excluded by user)      │
│      [Edit]                                 │
│                                             │
│ ☑ ○ Soft blue hijab with matching tunic    │
│      [Edit] Tags: modest, hijab             │
│                                             │
│ ☑ ○ Beige oversized sweater with skirt     │
│      [Edit] Tags: cute, comfortable         │
│                                             │
│ [← Back] [Save Selected (4)]                │
└─────────────────────────────────────────────┘
```

#### Livewire Component Methods

```php
class WardrobeManager extends Component
{
    // ... existing properties ...
    
    // New properties for generation
    public $showGenerateModal = false;
    public $generateSlot = null;
    public $generateCount = 5;
    public $selectedTags = [];
    public $customTags = [];
    public $generatedOutfits = [];
    public $showReviewModal = false;
    public $selectedForSave = []; // Indices of checked outfits
    public $primaryIndex = 0; // Which one is primary
    public $isGenerating = false;

    protected $rules = [
        // ... existing rules ...
        'generateCount' => 'required|integer|min:1|max:10',
        'selectedTags' => 'array',
        'customTags.*' => 'string|max:50',
    ];

    public function openGenerateModal($slot)
    {
        $this->generateSlot = $slot;
        $this->generateCount = 5;
        $this->selectedTags = [];
        $this->customTags = [];
        $this->showGenerateModal = true;
    }

    public function addCustomTag($tag)
    {
        if (!empty($tag) && !in_array($tag, $this->customTags)) {
            $this->customTags[] = $tag;
        }
    }

    public function removeCustomTag($index)
    {
        unset($this->customTags[$index]);
        $this->customTags = array_values($this->customTags);
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
            Log::error('Generation failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Failed to generate outfits. Please try again.');
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

    public function saveGeneratedOutfits()
    {
        if (empty($this->selectedForSave)) {
            session()->flash('error', 'Please select at least one outfit to save.');
            return;
        }
        
        $savedCount = 0;
        
        foreach ($this->selectedForSave as $index) {
            $outfit = $this->generatedOutfits[$index];
            
            WardrobeItem::create([
                'persona_id' => $this->persona->id,
                'slot_name' => $this->generateSlot,
                'is_primary' => ($index === $this->primaryIndex),
                ...$outfit
            ]);
            
            $savedCount++;
        }
        
        session()->flash('message', "{$savedCount} outfits added successfully!");
        
        $this->showReviewModal = false;
        $this->generatedOutfits = [];
        $this->selectedForSave = [];
    }

    public function closeGenerateModal()
    {
        $this->showGenerateModal = false;
        $this->showReviewModal = false;
        $this->generatedOutfits = [];
    }
}
```

#### Definition of Done
- Generate button visible on all slot cards
- Modal UI matches design spec
- Tag selection works (multi-select)
- Generation loading state displays
- Review screen shows all generated outfits
- Can check/uncheck individual outfits
- Primary selection works (radio buttons)
- Save button creates selected outfits in database
- Success/error messages display correctly
- Generated outfits appear immediately in wardrobe

---

### Story 4: Generate Similar Feature (3 points)
**Story ID:** PERSONA-WRD-002-S4  
**Priority:** P0  
**Dependencies:** Story 3

#### Description
Add "Generate Similar" button to each outfit card that creates 3 variations using the existing outfit's tags and description as reference.

#### Acceptance Criteria
- [ ] AC1: "🔄 Generate Similar" button added to outfit cards (shows on hover or always visible)
- [ ] AC2: Button opens review modal with 3 pre-generated variations
- [ ] AC3: Generated outfits:
  - Use same tags as original
  - Different colors/styles but similar vibe
  - Reference original description for inspiration
- [ ] AC4: Same review/save flow as bulk generation
- [ ] AC5: Original outfit preserved (not modified)
- [ ] AC6: Loading state during generation (3-5 seconds)

#### UI Changes

**Outfit Card with Generate Similar:**
```
┌─────────────────────────────────────────┐
│ ⭐ Primary (worn 12 times)              │
│ Blue jeans with white floral sundress   │
│ Tags: [cute] [casual]                   │
│ Last worn: Today                         │
│                                          │
│ [Edit] [🔄 Generate Similar]            │
└─────────────────────────────────────────┘
```

#### Livewire Method

```php
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
        session()->flash('error', 'Failed to generate similar outfits.');
    } finally {
        $this->isGenerating = false;
    }
}
```

#### Definition of Done
- Generate Similar button visible on outfit cards
- Click generates 3 variations
- Variations respect original outfit's style
- Review modal works same as bulk generation
- Can save 0-3 of the generated variations
- Original outfit unchanged

---

### Story 5: Emergency Fallback Generation (3 points)
**Story ID:** PERSONA-WRD-002-S5  
**Priority:** P0  
**Dependencies:** Story 2

#### Description
Modify `WardrobeService::selectOutfitForDay()` to automatically generate and save one outfit when slot is empty, preventing image generation failures.

#### Acceptance Criteria
- [ ] AC1: When `$outfits->isEmpty()` in `selectOutfitForDay()`, auto-generates 1 outfit
- [ ] AC2: Uses `getDefaultTags()` to infer persona style
- [ ] AC3: Saves generated outfit as `is_primary = true`
- [ ] AC4: Returns outfit immediately (seamless to image generation)
- [ ] AC5: Logs event: "Auto-generated fallback outfit for {slot}"
- [ ] AC6: Does NOT log to `wardrobe_generation_log` (this is automatic, not user-initiated)
- [ ] AC7: Image generation continues without interruption
- [ ] AC8: No user notification (silent fallback)

#### Code Changes

```php
// In WardrobeService::selectOutfitForDay()
public function selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem
{
    // ... existing cache check ...
    
    // Get available outfits
    $query = WardrobeItem::where('persona_id', $persona->id)
        ->where('slot_name', $slot);
    
    if ($yesterday) {
        $query->where('id', '!=', $yesterday);
    }
    
    $outfits = $query->get();
    
    // NEW: Emergency fallback generation
    if ($outfits->isEmpty()) {
        Log::info('WardrobeService: No outfits found, auto-generating fallback', [
            'persona_id' => $persona->id,
            'slot' => $slot,
        ]);
        
        $generated = $this->generateOutfits(
            $persona,
            $slot,
            $this->getDefaultTags($persona),
            1 // Just one outfit
        )->first();
        
        if (!$generated) {
            throw new \Exception("Failed to generate fallback outfit for persona {$persona->id}, slot: {$slot}");
        }
        
        // Auto-save as primary
        $outfit = WardrobeItem::create([
            'persona_id' => $persona->id,
            'slot_name' => $slot,
            'is_primary' => true,
            ...$generated
        ]);
        
        // Cache the selection
        DailyOutfitSelection::create([
            'persona_id' => $persona->id,
            'date' => $date->toDateString(),
            'slot_name' => $slot,
            'wardrobe_item_id' => $outfit->id,
        ]);
        
        return $outfit;
    }
    
    // ... rest of existing rotation logic ...
}
```

#### Testing Validation
```php
php artisan tinker

// Create persona with empty wardrobe
$persona = Persona::first();
WardrobeItem::where('persona_id', $persona->id)->delete();

// Trigger image generation
GeminiBrain::generateImage('SELFIE: smiling at camera', $persona);

// Check wardrobe
$persona->wardrobeItems; // Should have 1 auto-generated outfit

// Check logs
tail storage/logs/laravel.log // Should see "auto-generating fallback"
```

#### Definition of Done
- Empty wardrobe no longer causes image generation failure
- Auto-generated outfit saved as primary
- Logging confirms fallback generation
- Image generation completes successfully
- No user-facing errors or notifications

---

### Story 6: Tag Management UI (2 points)
**Story ID:** PERSONA-WRD-002-S6  
**Priority:** P1 (Nice to have)  
**Dependencies:** Story 1

#### Description
Add tag editing interface to add/edit outfit modal, allowing users to manage tags on new and existing outfits.

#### Acceptance Criteria
- [ ] AC1: Tag selector added to outfit modal (both add and edit)
- [ ] AC2: Predefined tags shown as clickable badges
- [ ] AC3: Selected tags highlighted
- [ ] AC4: Custom tag input field with "+ Add" button
- [ ] AC5: Tags saved to database on outfit save
- [ ] AC6: Tags displayed on outfit cards as colored badges
- [ ] AC7: Max 10 tags per outfit enforced

#### UI Design

**Modal with Tag Selector:**
```
┌─────────────────────────────────────────┐
│ Add/Edit Outfit                          │
├─────────────────────────────────────────┤
│ Description: [........................] │
│                                          │
│ Style Tags:                              │
│ [cute✓] [elegant] [modest✓] [hijab]    │
│ [formal] [casual] [sporty] [trendy]     │
│                                          │
│ Custom Tags:                             │
│ [Enter tag...] [+ Add]                   │
│ [minimalist] [x] [y2k] [x]              │
│                                          │
│ Upper Body: [......................]    │
│ ...                                      │
└─────────────────────────────────────────┘
```

**Outfit Card with Tags:**
```
┌─────────────────────────────────────────┐
│ Blue jeans with white floral sundress   │
│ [cute] [casual] [comfortable]           │
│ Last worn: Today                         │
└─────────────────────────────────────────┘
```

#### Definition of Done
- Tag selector visible in modal
- Can select/deselect predefined tags
- Can add custom tags
- Tags saved with outfit
- Tags display on outfit cards
- Tag limit (10) enforced with validation message

---

## Technical Dependencies

### Required Services
- ✅ Gemini 2.0 Flash API (already integrated)
- ✅ WardrobeService (from PERSONA-WRD-001)
- ✅ Livewire 3 (already installed)

### Configuration
Add to `.env` (optional, for future rate limiting):
```env
WARDROBE_GENERATION_DAILY_LIMIT=100
WARDROBE_MAX_OUTFITS_PER_GENERATION=10
```

---

## Testing Strategy

### Unit Tests
- `WardrobeServiceTest::test_generates_outfits_with_tags()`
- `WardrobeServiceTest::test_generates_similar_outfits()`
- `WardrobeServiceTest::test_emergency_fallback_generation()`
- `WardrobeServiceTest::test_rate_limit_check()`
- `WardrobeServiceTest::test_default_tags_extraction()`

### Integration Tests
- `WardrobeGenerationTest::test_full_bulk_generation_flow()`
- `WardrobeGenerationTest::test_generate_similar_flow()`
- `ImageGenerationTest::test_fallback_on_empty_wardrobe()`

### Manual Testing Checklist
- [ ] Generate 5 outfits with mixed tags → all saved correctly
- [ ] Generate similar from existing outfit → variations are distinct
- [ ] Empty wardrobe + image generation → auto-creates outfit
- [ ] Edit tags on existing outfit → changes persist
- [ ] Custom tags → saved and displayed
- [ ] Bulk save with primary selection → correct outfit marked primary
- [ ] Generation fails gracefully → error message shown
- [ ] Rate limit reached → friendly error (future)

---

## Deployment Plan

### Pre-Deployment
1. Backup `wardrobe_items` table
2. Run migration: `php artisan migrate`
3. Test generation in staging:
   ```bash
   php artisan tinker
   Wardrobe::generateOutfits(Persona::first(), 'casual_daytime', ['cute'], 3)
   ```
4. Verify Gemini API quota (expect higher usage)

### Post-Deployment Validation
1. Check generation logs: `tail -f storage/logs/laravel.log | grep "Generated outfits"`
2. Test emergency fallback: Delete all outfits, trigger image generation
3. Monitor `wardrobe_generation_log` table for usage patterns
4. User feedback on outfit quality

### Rollback Plan
- Migration reversible: `php artisan migrate:rollback`
- Emergency fallback can be disabled via feature flag if needed
- Old manual workflow still available

---

## Success Metrics (Track for 2 Weeks)

### Quantitative
- Average wardrobe setup time: Target < 1 minute per persona
- Generation success rate: Target > 95%
- Emergency fallback usage: Track how often it triggers
- User-initiated generations: Track daily usage patterns
- Outfits per slot: Target average 5+ per slot

### Qualitative
- User survey: Satisfaction with generated outfit quality (1-5 scale)
- Support tickets: Reduction in "no outfit" errors
- Feature adoption: % of users who try generation feature

---

## Future Enhancements (Not in Current Epic)

### Phase 2 Ideas
- **Smart Tag Suggestions:** ML-based tag recommendations
- **Outfit Combiner:** Mix parts from multiple outfits
- **Seasonal Generation:** "Generate summer wardrobe"
- **Image Upload:** Generate outfit from uploaded photo
- **Style Transfer:** Apply one persona's style to another
- **Community Tags:** Share and discover popular tags
- **Tag Analytics:** "Most popular tags", "Trending styles"

### Rate Limiting Enforcement
When ready to enforce (production scaling):
```php
// Update checkGenerationLimit()
const MAX_GENERATIONS_PER_DAY = 20; // Reduce from 100
// Add user notification when limit reached
```

---

## Open Questions & Decisions

### Resolved
- ✅ Emergency fallback: Silent with log only
- ✅ Rate limiting: Prepare now, enforce later
- ✅ Tags: Save to DB, editable anytime
- ✅ Generate Similar: Yes, 3 variations

### Pending
- ⚠️ Should we add "Regenerate" button in review modal if user doesn't like results?
- ⚠️ Default number of outfits to generate (currently 5)?
- ⚠️ Should emergency fallback generate multiple outfits or just one?
- ⚠️ Pricing concern: Each generation costs API credits - monitor usage?

---

## Story Assignment & Timeline

| Story | Points | Assigned To | Status | Est. Completion |
|-------|--------|-------------|--------|-----------------|
| S1: Tags + Logging | 3 | Party | Ready | Day 1 |
| S2: AI Service | 5 | Party | Ready | Day 1-2 |
| S3: Bulk Generation UI | 5 | Party | Ready | Day 2-3 |
| S4: Generate Similar | 3 | Party | Ready | Day 3 |
| S5: Emergency Fallback | 3 | Party | Ready | Day 3 |
| S6: Tag Management UI | 2 | Party | P1 | Day 4 |

**Total Sprint Duration:** 3-4 days for P0 stories (19 points)

---

## Definition of Epic Complete

- [ ] All P0 stories (S1-S5) marked as DONE
- [ ] Zero image generation failures due to empty wardrobe
- [ ] Users can generate 5+ outfits in under 1 minute
- [ ] Emergency fallback tested and working
- [ ] Documentation published
- [ ] No regression in existing wardrobe features
- [ ] Positive user feedback on generation quality

---

**Epic Created By:** BMad Master with Party Mode Team  
**Creation Date:** 2025-12-20  
**Last Updated:** 2025-12-20  
**Approved By:** Nazar (Product Owner)

---

*Let's make wardrobe setup instant! 🚀*
