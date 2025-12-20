<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Persona;
use App\Models\WardrobeItem;
use App\Models\WardrobeGenerationLog;
use App\Facades\Wardrobe;

echo "=== Story 2: Testing AI Outfit Generation ===" . PHP_EOL . PHP_EOL;

$persona = Persona::first();
if (!$persona) {
    echo "❌ No persona found. Create one first." . PHP_EOL;
    exit(1);
}

echo "✅ Persona: {$persona->name} (ID: {$persona->id})" . PHP_EOL;
echo "   Physical traits: {$persona->physical_traits}" . PHP_EOL;
echo "   Gender: {$persona->gender}" . PHP_EOL . PHP_EOL;

// Test 1: Get default tags
echo "Test 1: Extracting default tags from system prompt..." . PHP_EOL;
$defaultTags = Wardrobe::getDefaultTags($persona);
echo "✅ Default tags: " . json_encode($defaultTags) . PHP_EOL . PHP_EOL;

// Test 2: Check generation limit
echo "Test 2: Checking generation limit..." . PHP_EOL;
$canGenerate = Wardrobe::checkGenerationLimit($persona);
echo ($canGenerate ? "✅" : "❌") . " Can generate: " . ($canGenerate ? "Yes" : "No") . PHP_EOL;

// Check today's usage
$today = \Carbon\Carbon::today();
$todayCount = WardrobeGenerationLog::where('persona_id', $persona->id)
    ->whereDate('generated_at', '>=', $today)
    ->sum('outfits_generated');
echo "   Today's generation count: {$todayCount}/100" . PHP_EOL . PHP_EOL;

// Test 3: Generate outfits with AI
echo "Test 3: Generating 3 outfits with Gemini..." . PHP_EOL;
echo "   Tags: cute, casual" . PHP_EOL;
echo "   Slot: casual_daytime" . PHP_EOL;
echo "   Calling Gemini API... (this may take 5-10 seconds)" . PHP_EOL;

try {
    $outfits = Wardrobe::generateOutfits(
        $persona,
        'casual_daytime',
        ['cute', 'casual'],
        3
    );

    echo "✅ Generated {$outfits->count()} outfits!" . PHP_EOL . PHP_EOL;

    foreach ($outfits as $index => $outfit) {
        echo "   Outfit " . ($index + 1) . ":" . PHP_EOL;
        echo "   - Description: {$outfit['description']}" . PHP_EOL;
        echo "   - Upper: {$outfit['upper_body']}" . PHP_EOL;
        echo "   - Lower: " . ($outfit['lower_body'] ?? 'null (dress/jumpsuit)') . PHP_EOL;
        echo "   - Footwear: {$outfit['footwear']}" . PHP_EOL;
        echo "   - Accessories: " . ($outfit['accessories'] ?? 'null') . PHP_EOL;
        echo "   - Tags: " . json_encode($outfit['tags']) . PHP_EOL;
        echo PHP_EOL;
    }

    // Test 4: Save one outfit to database
    echo "Test 4: Saving first outfit to database..." . PHP_EOL;
    $saved = WardrobeItem::create([
        'persona_id' => $persona->id,
        'slot_name' => 'casual_daytime',
        'is_primary' => false,
        ...$outfits->first()
    ]);
    echo "✅ Saved outfit ID: {$saved->id}" . PHP_EOL;
    echo "   Tags in DB: " . json_encode($saved->tags) . PHP_EOL . PHP_EOL;

    // Test 5: Generate similar outfits
    echo "Test 5: Generating 2 similar outfits..." . PHP_EOL;
    $similar = Wardrobe::generateSimilarOutfits($saved, 2);
    echo "✅ Generated {$similar->count()} similar outfits!" . PHP_EOL . PHP_EOL;

    foreach ($similar as $index => $outfit) {
        echo "   Similar Outfit " . ($index + 1) . ":" . PHP_EOL;
        echo "   - Description: {$outfit['description']}" . PHP_EOL;
        echo "   - Tags: " . json_encode($outfit['tags']) . PHP_EOL;
        echo PHP_EOL;
    }

    // Test 6: Check generation log
    echo "Test 6: Verifying generation log..." . PHP_EOL;
    $logs = WardrobeGenerationLog::where('persona_id', $persona->id)
        ->latest('generated_at')
        ->take(2)
        ->get();

    echo "✅ Found {$logs->count()} recent generation logs:" . PHP_EOL;
    foreach ($logs as $log) {
        echo "   - Slot: {$log->slot_name}, Tags: " . json_encode($log->tags_used) . ", Count: {$log->outfits_generated}" . PHP_EOL;
    }

    echo PHP_EOL . "=== All Story 2 Tests Complete! ===" . PHP_EOL;
    echo PHP_EOL . "Cleanup (deleting test outfit)..." . PHP_EOL;
    $saved->delete();
    echo "✅ Test data cleaned up" . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Generation failed: " . $e->getMessage() . PHP_EOL;
    echo "   Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
