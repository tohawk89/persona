<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Persona;
use App\Facades\GeminiBrain;
use App\Facades\Telegram;
use Illuminate\Support\Facades\Log;

echo "🔍 Finding persona 'Hana'...\n";

$persona = Persona::where('name', 'Hana')->first();

if (!$persona) {
    echo "❌ Persona 'Hana' not found.\n";
    echo "📋 Available personas:\n";
    $personas = Persona::all();
    foreach ($personas as $p) {
        echo "   - {$p->name} (ID: {$p->id})\n";
    }
    exit(1);
}

echo "✅ Found persona: {$persona->name} (ID: {$persona->id})\n";

// Check if persona has avatar
$avatar = $persona->getFirstMedia('avatar');
if (!$avatar) {
    echo "⚠️ Warning: Persona has no avatar. Image generation may fail.\n";
} else {
    echo "✅ Avatar found: {$avatar->getUrl()}\n";
}

// Check Telegram chat ID
$chatId = $persona->user->telegram_chat_id ?? null;
if (!$chatId) {
    echo "❌ No Telegram chat ID configured for this persona's user.\n";
    exit(1);
}

echo "✅ Telegram Chat ID: {$chatId}\n";
echo "\n";

// Test image generation
echo "🎨 Generating test image...\n";
$prompt = "Taking a selfie at a cozy coffee shop, smiling warmly";

try {
    $imageUrl = GeminiBrain::generateImage($prompt, $persona);
    
    if (!$imageUrl) {
        echo "❌ Image generation failed (returned null)\n";
        exit(1);
    }
    
    echo "✅ Image generated successfully!\n";
    echo "   URL: {$imageUrl}\n";
    echo "\n";
    
    // Send to Telegram
    echo "📤 Sending image to Telegram...\n";
    
    $result = Telegram::sendPhoto($chatId, $imageUrl, "Here's a selfie from the coffee shop! ☕️");
    
    if ($result) {
        echo "✅ Image sent successfully to Telegram!\n";
        echo "   Check your Telegram chat with the bot.\n";
    } else {
        echo "❌ Failed to send image to Telegram.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Trace: {$e->getTraceAsString()}\n";
}

echo "\n✅ Test completed!\n";
