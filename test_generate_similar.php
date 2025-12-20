<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Persona;
use App\Models\WardrobeItem;
use App\Facades\Wardrobe;

echo "=== Story 4: Testing Generate Similar ===" . PHP_EOL . PHP_EOL;

$persona = Persona::first();
if (!$persona) {
    echo "❌ No persona found. Create one first." . PHP_EOL;
    exit(1);
}

echo "✅ Persona: {$persona->name} (ID: {$persona->id})" . PHP_EOL . PHP_EOL;

// Test 1: Create a test outfit to use as reference
echo "Test 1: Creating reference outfit..." . PHP_EOL;
$referenceOutfit = WardrobeItem::create([
    'persona_id' => $persona->id,
    'slot_name' => 'casual_daytime',
    'description' => 'Light blue denim jacket with white tee, black jeans and white sneakers',
    'upper_body' => 'Light blue denim jacket with white tee',
    'lower_body' => 'Black jeans',
    'footwear' => 'White sneakers',
    'tags' => ['casual', 'comfortable'],
    'is_primary' => false,
]);

echo "✅ Created reference outfit ID: {$referenceOutfit->id}" . PHP_EOL;
echo "   Description: {$referenceOutfit->description}" . PHP_EOL;
echo "   Tags: " . json_encode($referenceOutfit->tags) . PHP_EOL . PHP_EOL;

// Test 2: Generate similar variations
echo "Test 2: Generating 3 similar variations..." . PHP_EOL;
echo "   Using reference outfit as style guide" . PHP_EOL;
echo "   Calling Gemini API... (5-10 seconds)" . PHP_EOL;

try {
    $similar = Wardrobe::generateSimilarOutfits($referenceOutfit, 3);

    echo "✅ Generated {$similar->count()} similar outfits!" . PHP_EOL . PHP_EOL;

    foreach ($similar as $index => $outfit) {
        echo "   Similar Outfit " . ($index + 1) . ":" . PHP_EOL;
        echo "   - Description: {$outfit['description']}" . PHP_EOL;
        echo "   - Upper: {$outfit['upper_body']}" . PHP_EOL;
        echo "   - Lower: " . ($outfit['lower_body'] ?? 'null') . PHP_EOL;
        echo "   - Footwear: {$outfit['footwear']}" . PHP_EOL;
        echo "   - Tags: " . json_encode($outfit['tags']) . PHP_EOL;
        echo PHP_EOL;
    }

    // Test 3: Verify original outfit unchanged
    echo "Test 3: Verifying original outfit unchanged..." . PHP_EOL;
    $refreshed = WardrobeItem::find($referenceOutfit->id);
    echo "✅ Original outfit still exists:" . PHP_EOL;
    echo "   ID: {$refreshed->id}" . PHP_EOL;
    echo "   Description: {$refreshed->description}" . PHP_EOL;
    echo "   Tags: " . json_encode($refreshed->tags) . PHP_EOL . PHP_EOL;

    // Test 4: Save one variation to database
    echo "Test 4: Saving first variation to database..." . PHP_EOL;
    $saved = WardrobeItem::create([
        'persona_id' => $persona->id,
        'slot_name' => 'casual_daytime',
        'is_primary' => false,
        ...$similar->first()
    ]);
    echo "✅ Saved variation ID: {$saved->id}" . PHP_EOL;
    echo "   Tags match: " . (json_encode($saved->tags) === json_encode($referenceOutfit->tags) ? 'Yes' : 'No') . PHP_EOL;
    echo PHP_EOL;

    // Test 5: Compare styles
    echo "Test 5: Style comparison..." . PHP_EOL;
    echo "   Reference: Denim jacket style, casual/comfortable" . PHP_EOL;
    echo "   Variation 1: " . substr($similar->first()['description'], 0, 50) . "..." . PHP_EOL;
    echo "   ✅ Should have similar casual vibe but different details" . PHP_EOL;
    echo PHP_EOL;

    echo "=== All Story 4 Tests Complete! ===" . PHP_EOL;
    echo PHP_EOL . "Cleanup (deleting test outfits)..." . PHP_EOL;
    $referenceOutfit->delete();
    $saved->delete();
    echo "✅ Test data cleaned up" . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ Generation failed: " . $e->getMessage() . PHP_EOL;
    echo "   Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;

    // Cleanup on error
    $referenceOutfit->delete();
    exit(1);
}
