# Epic: Wardrobe Inventory System with Dynamic Rotation

**Epic ID:** PERSONA-WRD-001  
**Priority:** High  
**Estimated Total:** 24 Story Points  
**Target Sprint:** Sprint 2025-W51  
**Status:** Ready for Development

---

## Epic Overview

### Business Goal
Implement a structured wardrobe inventory system that replaces fragile memory tag-based outfit management with persistent, rotatable outfit collections per persona, ensuring consistent and realistic image generation.

### Success Metrics
- ✅ 100% of image generations use wardrobe data instead of memory tags
- ✅ Outfit variety: personas wear different outfits across 7-day period
- ✅ Same-day consistency: outfit doesn't change within a day
- ✅ Zero "missing outfit" errors in image generation logs

### User Value
"As a user, I want my persona to have a realistic wardrobe with rotating outfits, so that generated images show variety while maintaining consistency throughout each day."

---

## Architecture Summary

### Database Schema
```sql
CREATE TABLE wardrobe_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    persona_id BIGINT UNSIGNED NOT NULL,
    slot_name ENUM('casual_daytime', 'casual_nighttime', 'formal', 'workout', 'sleepwear', 'custom') NOT NULL,
    description TEXT NOT NULL COMMENT 'Full outfit description',
    upper_body VARCHAR(255) NULL COMMENT 'Top/dress only',
    lower_body VARCHAR(255) NULL COMMENT 'Pants/skirt (null for dresses)',
    footwear VARCHAR(255) NULL COMMENT 'Shoes/sandals',
    accessories TEXT NULL COMMENT 'Jewelry, bags, etc.',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'The main outfit for this slot',
    last_worn_at TIMESTAMP NULL COMMENT 'Last time this outfit was selected',
    wear_count INT DEFAULT 0 COMMENT 'Total times worn',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    INDEX idx_persona_slot (persona_id, slot_name),
    INDEX idx_last_worn (persona_id, slot_name, last_worn_at)
);

CREATE TABLE daily_outfit_selections (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    persona_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    slot_name VARCHAR(50) NOT NULL,
    wardrobe_item_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    FOREIGN KEY (wardrobe_item_id) REFERENCES wardrobe_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_persona_date_slot (persona_id, date, slot_name)
);
```

### Service Layer
**New Service:** `App\Services\WardrobeService` (registered as singleton)

**Key Methods:**
- `getTodaysOutfit(Persona $persona, string $timeContext): ?WardrobeItem`
- `selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem`
- `buildOutfitDescription(WardrobeItem $item, string $shotType): string`
- `setOutfit(int $personaId, string $slot, array $parts, bool $isPrimary): WardrobeItem`
- `getOutfitHistory(int $personaId, int $days = 7): Collection`

### Integration Points
1. `GeminiBrainService::buildImagePrompt()` - Replace `getCurrentOutfit()` call with `WardrobeService::getTodaysOutfit()`
2. `GeminiBrainService::getCurrentOutfit()` - Deprecate in favor of WardrobeService
3. Dashboard - New route `/wardrobe` with Livewire component

---

## Story Breakdown

### Story 1: Database Schema & Model
**Story ID:** PERSONA-WRD-001-S1  
**Points:** 3  
**Priority:** P0 (Blocker for other stories)

#### Description
Create database migration for wardrobe_items and daily_outfit_selections tables, plus WardrobeItem model with relationships.

#### Acceptance Criteria
- [ ] AC1: Migration creates `wardrobe_items` table with all specified columns and indexes
- [ ] AC2: Migration creates `daily_outfit_selections` table for caching daily choices
- [ ] AC3: `WardrobeItem` model created with `belongsTo(Persona)` relationship
- [ ] AC4: `Persona` model has `hasMany(WardrobeItem)` relationship
- [ ] AC5: Model includes fillable fields and casts (is_primary as boolean, last_worn_at as datetime)
- [ ] AC6: Factory created for testing with realistic outfit data
- [ ] AC7: Migration runs cleanly: `php artisan migrate` succeeds

#### Technical Notes
- Use enum for `slot_name` to prevent invalid values
- Add composite index on (persona_id, slot_name, last_worn_at) for rotation queries
- Model should use `$dates = ['last_worn_at']` for Carbon casting

#### Definition of Done
- Migration file in `database/migrations/`
- Model in `app/Models/WardrobeItem.php`
- Factory in `database/factories/WardrobeItemFactory.php`
- Relationships tested in tinker
- No errors when running `php artisan migrate:fresh`

---

### Story 2: WardrobeService Implementation
**Story ID:** PERSONA-WRD-001-S2  
**Points:** 7  
**Priority:** P0  
**Dependencies:** Story 1

#### Description
Create WardrobeService with outfit selection logic, rotation algorithm, and daily caching to ensure same-day consistency.

#### Acceptance Criteria
- [ ] AC1: Service registered as singleton in `AppServiceProvider`
- [ ] AC2: `getTodaysOutfit($persona, $timeContext)` returns cached outfit for today
  - Time context: 'daytime' (6am-9pm) → casual_daytime slot
  - Time context: 'nighttime' (9pm-6am) → casual_nighttime slot
- [ ] AC3: `selectOutfitForDay()` implements 70/30 rotation logic:
  - 70% probability: select primary outfit (where is_primary = true)
  - 30% probability: select random from rotation pool
  - Never select yesterday's outfit (check last_worn_at)
  - If only one outfit exists, always return it
- [ ] AC4: `buildOutfitDescription($item, $shotType)` filters parts:
  - Shot type "close-up portrait" → upper_body only
  - Shot type "medium shot" → upper_body + accessories
  - Shot type "full body" → all parts
  - Returns formatted string: "white floral sundress with sandals"
- [ ] AC5: Daily selection cached in `daily_outfit_selections` table
- [ ] AC6: Updates `last_worn_at` and increments `wear_count` when outfit selected
- [ ] AC7: Facade created: `App\Facades\Wardrobe`

#### Technical Implementation Details

**Selection Algorithm:**
```php
public function selectOutfitForDay(Persona $persona, string $slot, Carbon $date): WardrobeItem
{
    // Check if already selected today
    $cached = DailyOutfitSelection::where('persona_id', $persona->id)
        ->where('date', $date->toDateString())
        ->where('slot_name', $slot)
        ->first();
    
    if ($cached) {
        return $cached->wardrobeItem;
    }
    
    // Get yesterday's outfit to avoid repeat
    $yesterday = DailyOutfitSelection::where('persona_id', $persona->id)
        ->where('date', $date->copy()->subDay()->toDateString())
        ->where('slot_name', $slot)
        ->first()?->wardrobe_item_id;
    
    // Get available outfits (excluding yesterday's)
    $query = WardrobeItem::where('persona_id', $persona->id)
        ->where('slot_name', $slot);
    
    if ($yesterday) {
        $query->where('id', '!=', $yesterday);
    }
    
    $outfits = $query->get();
    
    if ($outfits->isEmpty()) {
        throw new \Exception("No outfits found for slot: {$slot}");
    }
    
    // 70% chance: use primary, 30% random
    $selected = (rand(1, 100) <= 70)
        ? $outfits->where('is_primary', true)->first() ?? $outfits->random()
        : $outfits->random();
    
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
    
    return $selected;
}
```

#### Unit Tests Required
- `WardrobeServiceTest::test_selects_primary_outfit_70_percent_of_time()`
- `WardrobeServiceTest::test_never_repeats_yesterdays_outfit()`
- `WardrobeServiceTest::test_caches_daily_selection()`
- `WardrobeServiceTest::test_filters_outfit_by_shot_type()`
- `WardrobeServiceTest::test_handles_single_outfit_gracefully()`

#### Definition of Done
- Service file: `app/Services/WardrobeService.php`
- Facade file: `app/Facades/Wardrobe.php`
- Registered in AppServiceProvider
- All 5 unit tests passing
- Can be called via `Wardrobe::getTodaysOutfit($persona, 'daytime')`

---

### Story 3: Image Generation Integration
**Story ID:** PERSONA-WRD-001-S3  
**Points:** 3  
**Priority:** P0  
**Dependencies:** Story 2

#### Description
Update GeminiBrainService to use WardrobeService instead of memory tags for outfit retrieval during image generation.

#### Acceptance Criteria
- [ ] AC1: `GeminiBrainService::buildImagePrompt()` line 868 replaced:
  - OLD: `$currentOutfit = $this->getCurrentOutfit($persona->id);`
  - NEW: `$currentOutfit = Wardrobe::getTodaysOutfit($persona, $timeContext)?->description;`
- [ ] AC2: Time context determined by current hour:
  - 6am-9pm → 'daytime'
  - 9pm-6am → 'nighttime'
- [ ] AC3: Shot type filtering uses `Wardrobe::buildOutfitDescription()`:
  - Line 876 updated to use service method
- [ ] AC4: `getCurrentOutfit()` method marked as deprecated with PHPDoc:
  ```php
  /**
   * @deprecated Use WardrobeService::getTodaysOutfit() instead
   */
  ```
- [ ] AC5: Regression test: Existing image generation tests still pass
- [ ] AC6: Log message updated: `"Using wardrobe outfit"` instead of `"Using memory tag outfit"`

#### Files to Modify
- `app/Services/GeminiBrainService.php` (lines 868, 876, and getCurrentOutfit method)

#### Testing Validation
```php
php artisan tinker
$persona = Persona::first();
$url = GeminiBrain::generateImage('SELFIE: smiling at camera', $persona);
// Check logs for "Using wardrobe outfit" message
// Verify generated image has correct outfit
```

#### Definition of Done
- GeminiBrainService uses WardrobeService
- No memory tag dependency for outfits
- All existing tests pass
- Manual image generation test succeeds with wardrobe outfit

---

### Story 4: Dashboard UI - Wardrobe Manager
**Story ID:** PERSONA-WRD-001-S4  
**Points:** 8  
**Priority:** P1  
**Dependencies:** Story 2

#### Description
Create Livewire component for wardrobe management with primary outfit + rotation pool UI, supporting CRUD operations and preview functionality.

#### Acceptance Criteria
- [ ] AC1: New menu item "Wardrobe" in dashboard navigation
- [ ] AC2: Route `/wardrobe` registered in `routes/web.php`
- [ ] AC3: `WardrobeManager` Livewire component created
- [ ] AC4: UI displays outfit slots as cards:
  - Casual Daytime
  - Casual Nighttime
  - Formal
  - Workout
  - Sleepwear
- [ ] AC5: Each card shows:
  - Slot name badge
  - Primary outfit description (highlighted)
  - Rotation pool items (collapsible list)
  - Last worn timestamp
  - Wear count
  - Edit/Delete actions
- [ ] AC6: "Add Outfit" modal form with fields:
  - Full Description (textarea, required)
  - Upper Body (text input)
  - Lower Body (text input)
  - Footwear (text input)
  - Accessories (textarea)
  - "Set as Primary" checkbox
  - AI Parse button (auto-fills parts from full description)
- [ ] AC7: "Preview" button generates sample image with selected outfit
- [ ] AC8: Validation rules:
  - Full description minimum 10 characters
  - At least one outfit per slot required
  - Only one primary per slot
- [ ] AC9: Delete confirmation modal with warning if deleting primary outfit

#### UI Design Specs

**Card Layout:**
```
┌──────────────────────────────────────────┐
│ 🌞 CASUAL DAYTIME                        │
├──────────────────────────────────────────┤
│ ⭐ Primary (worn 12 times)               │
│ Blue jeans with white floral sundress    │
│ and sandals                               │
│ Last worn: Today                          │
│                                           │
│ ─ Rotation Pool (2 items) ───────        │
│ • Red summer dress with wedge heels       │
│   Last worn: 2 days ago                   │
│ • Track pants and sports tee              │
│   Last worn: 5 days ago                   │
│                                           │
│ [+ Add Outfit] [Preview Primary]          │
└──────────────────────────────────────────┘
```

#### Livewire Component Methods
```php
class WardrobeManager extends Component
{
    public $selectedSlot;
    public $showModal = false;
    public $form = [
        'description' => '',
        'upper_body' => '',
        'lower_body' => '',
        'footwear' => '',
        'accessories' => '',
        'is_primary' => false,
    ];
    
    public function addOutfit($slot) { }
    public function editOutfit($id) { }
    public function deleteOutfit($id) { }
    public function setPrimary($id) { }
    public function parseDescription() { } // AI-assisted parsing
    public function previewOutfit($id) { } // Generate sample image
}
```

#### Definition of Done
- Livewire component: `app/Livewire/WardrobeManager.php`
- Blade view: `resources/views/livewire/wardrobe-manager.blade.php`
- Route added to `routes/web.php`
- Menu item in `resources/views/layouts/navigation.blade.php`
- All CRUD operations functional
- Preview generates actual image
- Responsive design (mobile + desktop)

---

### Story 5: Data Migration from Memory Tags
**Story ID:** PERSONA-WRD-001-S5  
**Points:** 2  
**Priority:** P1  
**Dependencies:** Story 1, Story 2

#### Description
Create Artisan command to migrate existing `daily_outfit` and `night_outfit` from memory_tags to wardrobe_items table, preserving current persona outfits.

#### Acceptance Criteria
- [ ] AC1: Command signature: `php artisan app:migrate-outfits-from-memory`
- [ ] AC2: Finds all personas with memory tags:
  - category = 'daily_outfit'
  - category = 'night_outfit'
- [ ] AC3: Creates wardrobe_items:
  - daily_outfit → casual_daytime slot (is_primary = true)
  - night_outfit → casual_nighttime slot (is_primary = true)
- [ ] AC4: Parses outfit description to extract parts:
  - Uses regex or simple string split
  - Fills upper_body, lower_body, footwear if detectable
- [ ] AC5: Progress bar shows migration status
- [ ] AC6: Dry-run option: `--dry-run` flag to preview without committing
- [ ] AC7: Summary output:
  ```
  Migrating outfits from memory tags...
  ✓ Persona 1: Hana - 2 outfits migrated
  ✓ Persona 2: Aisha - 2 outfits migrated
  
  Summary: 4 outfits migrated for 2 personas
  ```
- [ ] AC8: Idempotent: Can run multiple times without duplicates

#### Command Implementation
```php
namespace App\Console\Commands;

class MigrateOutfitsFromMemory extends Command
{
    protected $signature = 'app:migrate-outfits-from-memory {--dry-run}';
    protected $description = 'Migrate daily_outfit and night_outfit from memory_tags to wardrobe_items';
    
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $personas = Persona::all();
        
        $this->info('Migrating outfits from memory tags...');
        
        foreach ($personas as $persona) {
            $dayOutfit = MemoryTag::where('persona_id', $persona->id)
                ->where('category', 'daily_outfit')
                ->first();
            
            $nightOutfit = MemoryTag::where('persona_id', $persona->id)
                ->where('category', 'night_outfit')
                ->first();
            
            $migratedCount = 0;
            
            if ($dayOutfit && !$dryRun) {
                WardrobeItem::updateOrCreate([
                    'persona_id' => $persona->id,
                    'slot_name' => 'casual_daytime',
                    'is_primary' => true,
                ], [
                    'description' => $dayOutfit->value,
                    'upper_body' => $this->extractUpperBody($dayOutfit->value),
                    'lower_body' => $this->extractLowerBody($dayOutfit->value),
                    'footwear' => $this->extractFootwear($dayOutfit->value),
                ]);
                $migratedCount++;
            }
            
            if ($nightOutfit && !$dryRun) {
                WardrobeItem::updateOrCreate([
                    'persona_id' => $persona->id,
                    'slot_name' => 'casual_nighttime',
                    'is_primary' => true,
                ], [
                    'description' => $nightOutfit->value,
                    'upper_body' => $this->extractUpperBody($nightOutfit->value),
                    'lower_body' => $this->extractLowerBody($nightOutfit->value),
                    'footwear' => $this->extractFootwear($nightOutfit->value),
                ]);
                $migratedCount++;
            }
            
            if ($migratedCount > 0) {
                $this->info("✓ Persona {$persona->id}: {$persona->name} - {$migratedCount} outfits migrated");
            }
        }
        
        $this->info('Migration complete!');
    }
}
```

#### Definition of Done
- Command file: `app/Console/Commands/MigrateOutfitsFromMemory.php`
- Command runs successfully: `php artisan app:migrate-outfits-from-memory`
- Dry-run works: `php artisan app:migrate-outfits-from-memory --dry-run`
- Existing outfits preserved after migration
- Documentation added to `docs/guides/`

---

### Story 6: Outfit History & Analytics
**Story ID:** PERSONA-WRD-001-S6  
**Points:** 3  
**Priority:** P2 (Nice to have)  
**Dependencies:** Story 2, Story 4

#### Description
Add "Outfit History" view to dashboard showing what persona wore each day, with analytics on favorite outfits and wear frequency.

#### Acceptance Criteria
- [ ] AC1: "History" tab in WardrobeManager component
- [ ] AC2: Calendar view showing last 30 days
- [ ] AC3: Each day shows:
  - Date
  - Outfit worn (description)
  - Thumbnail if images were generated that day
- [ ] AC4: Analytics panel:
  - Most worn outfit (by wear_count)
  - Least worn outfit
  - Average days between outfit repeats
- [ ] AC5: Export CSV button for outfit history

#### Definition of Done
- History tab functional in wardrobe UI
- Analytics accurate based on daily_outfit_selections data
- CSV export works

---

## Technical Dependencies

### Required Laravel Packages
- ✅ Laravel 12 (already installed)
- ✅ Livewire 3 (already installed)
- ✅ Spatie MediaLibrary (already installed)

### Service Registration
Add to `app/Providers/AppServiceProvider.php`:
```php
$this->app->singleton(WardrobeService::class, function ($app) {
    return new WardrobeService();
});
```

---

## Testing Strategy

### Unit Tests
- `WardrobeServiceTest` (5 tests minimum)
- `WardrobeItemTest` (model relationships)

### Integration Tests
- `ImageGenerationWithWardrobeTest` (full flow)
- `WardrobeManagerLivewireTest` (UI CRUD)

### Manual Testing Checklist
- [ ] Add outfit via dashboard → appears in database
- [ ] Generate image → uses wardrobe outfit
- [ ] Generate multiple images same day → same outfit
- [ ] Generate image next day → different outfit (70% chance)
- [ ] Delete primary outfit → system handles gracefully
- [ ] Migration command → preserves existing data

---

## Deployment Plan

### Pre-Deployment
1. Backup `memory_tags` table
2. Run migration: `php artisan migrate`
3. Run seeder/migration command: `php artisan app:migrate-outfits-from-memory --dry-run`
4. Verify dry-run output
5. Run actual migration: `php artisan app:migrate-outfits-from-memory`

### Post-Deployment Validation
1. Check logs for "Using wardrobe outfit" messages
2. Generate test images for each persona
3. Verify outfit consistency throughout day
4. Monitor for "No outfits found" errors

### Rollback Plan
If issues occur:
1. Revert code deployment
2. `GeminiBrainService` falls back to `getCurrentOutfit()` method
3. Memory tags still intact as backup

---

## Open Questions & Decisions Needed

### Resolved
- ✅ Dynamic rotation vs static: **DYNAMIC (70/30 split)**
- ✅ Database schema: **Approved by architect**
- ✅ UI design: **Card-based with primary + pool**

### Pending
- ⚠️ Should we allow custom slot names beyond the 5 presets?
- ⚠️ AI parsing of outfit descriptions - which model? (Gemini or separate?)
- ⚠️ Preview image generation - use actual Kie.ai or mock?

---

## Story Assignment & Timeline

| Story | Points | Assigned To | Status | Est. Completion |
|-------|--------|-------------|--------|-----------------|
| S1: Schema & Model | 3 | Amelia | Ready | Day 1 |
| S2: Service | 7 | Amelia | Ready | Day 1-2 |
| S3: Integration | 3 | Amelia | Ready | Day 2 |
| S4: Dashboard UI | 8 | Amelia | Ready | Day 3-4 |
| S5: Migration | 2 | Amelia | Ready | Day 4 |
| S6: History | 3 | Amelia | P2 | Future |

**Total Sprint Duration:** 4-5 days for P0/P1 stories (21 points)

---

## Success Criteria

### Definition of Epic Complete
- [ ] All P0 and P1 stories marked as DONE
- [ ] Zero image generation failures due to missing outfits
- [ ] Dashboard wardrobe manager functional for all personas
- [ ] Migration command successfully run in production
- [ ] User documentation published
- [ ] No regression in existing features

### KPIs to Track Post-Launch
- Image generation success rate (target: 100%)
- Outfit variety score (unique outfits per 7-day period, target: ≥3)
- User engagement with wardrobe manager (dashboard visits)
- Average outfit changes per persona per week

---

## Documentation Deliverables

1. **User Guide:** "Managing Your Persona's Wardrobe"
2. **Developer Docs:** "Wardrobe Service API Reference"
3. **Migration Guide:** "Upgrading from Memory Tags"
4. **Architecture Decision Record:** "Why Dynamic Rotation vs Static Slots"

---

**Story Created By:** Bob (Scrum Master) with Party Mode Team  
**Creation Date:** 2025-12-20  
**Last Updated:** 2025-12-20  
**Approved By:** Nazar (Product Owner)

---

*Ready for development! Let's build this! 🚀*
