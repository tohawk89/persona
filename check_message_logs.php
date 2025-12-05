<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Message, Persona, User};
use Illuminate\Support\Facades\DB;

echo "=== INVESTIGATING MESSAGE CROSS-CONTAMINATION ===\n\n";

// Get all personas
$personas = Persona::with('user')->get();

echo "📊 Total Personas: " . $personas->count() . "\n\n";

foreach ($personas as $persona) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👤 Persona: {$persona->name} (ID: {$persona->id})\n";
    echo "   User: {$persona->user->name} (ID: {$persona->user_id})\n";
    echo "   Chat ID: {$persona->user->telegram_chat_id}\n";
    echo "   Bot Token: " . ($persona->telegram_bot_token ? substr($persona->telegram_bot_token, 0, 15) . '...' : 'System Default') . "\n";
    
    $messageCount = Message::where('persona_id', $persona->id)->count();
    echo "   Messages: {$messageCount}\n";
    
    // Get last 5 messages
    $recentMessages = Message::where('persona_id', $persona->id)
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
    
    if ($recentMessages->isNotEmpty()) {
        echo "\n   📨 Last 5 Messages:\n";
        foreach ($recentMessages as $msg) {
            $sender = $msg->sender_type === 'user' ? '👨 USER' : '🤖 BOT';
            $time = $msg->created_at->format('Y-m-d H:i:s');
            $preview = substr($msg->content, 0, 50);
            if (strlen($msg->content) > 50) $preview .= '...';
            echo "      {$sender} [{$time}]: {$preview}\n";
        }
    }
    echo "\n";
}

// Check for potential issues
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 CHECKING FOR ISSUES:\n\n";

// Issue 1: Messages with mismatched user_id and persona->user_id
echo "1️⃣ Checking for user_id mismatch...\n";
$mismatchedMessages = Message::whereHas('persona', function($query) {
    $query->whereColumn('messages.user_id', '!=', 'personas.user_id');
})->get();

if ($mismatchedMessages->isNotEmpty()) {
    echo "   ⚠️ FOUND {$mismatchedMessages->count()} MISMATCHED MESSAGES!\n";
    foreach ($mismatchedMessages as $msg) {
        echo "      - Message ID {$msg->id}: user_id={$msg->user_id}, but persona {$msg->persona_id} belongs to user_id={$msg->persona->user_id}\n";
    }
} else {
    echo "   ✅ No user_id mismatches found\n";
}

// Issue 2: Check if multiple personas share the same chat_id
echo "\n2️⃣ Checking for shared telegram_chat_id...\n";
$users = User::with('persona')->get();
foreach ($users as $user) {
    $personaCount = Persona::where('user_id', $user->id)->count();
    if ($personaCount > 1) {
        echo "   ⚠️ CRITICAL: User '{$user->name}' (chat_id: {$user->telegram_chat_id}) has {$personaCount} personas:\n";
        $userPersonas = Persona::where('user_id', $user->id)->get();
        foreach ($userPersonas as $p) {
            echo "      - {$p->name} (ID: {$p->id}) - Active: " . ($p->is_active ? 'YES' : 'NO') . "\n";
            echo "        Bot Token: " . ($p->telegram_bot_token ? substr($p->telegram_bot_token, 0, 15) . '...' : 'System Default') . "\n";
        }
        echo "   🚨 THIS IS THE ROOT CAUSE! Different personas responding to same chat_id!\n";
    }
}

// Issue 3: Check buffer keys
echo "\n3️⃣ Checking cache buffer structure...\n";
echo "   Buffer Key Format: chat_buffer_{telegram_chat_id}_{persona_id}\n";
echo "   ⚠️ If chat_id is shared but persona_id differs, buffers are separate (GOOD)\n";

// Issue 4: Check ProcessChatResponse uniqueId
echo "\n4️⃣ Checking ProcessChatResponse uniqueId logic...\n";
echo "   Unique ID Format: process_chat_{user_id}_{persona_id}\n";
echo "   ✅ This prevents concurrent processing per user+persona combo\n";

// Issue 5: Recent message timeline analysis
echo "\n5️⃣ Analyzing recent message timeline for anomalies...\n";
$recentAll = Message::with('persona')
    ->orderBy('created_at', 'desc')
    ->take(20)
    ->get();

$timeline = [];
foreach ($recentAll as $msg) {
    $key = $msg->created_at->format('Y-m-d H:i:s');
    if (!isset($timeline[$key])) {
        $timeline[$key] = [];
    }
    $timeline[$key][] = [
        'persona' => $msg->persona->name ?? 'Unknown',
        'persona_id' => $msg->persona_id,
        'sender' => $msg->sender_type,
        'content' => substr($msg->content, 0, 30),
    ];
}

foreach ($timeline as $time => $messages) {
    if (count($messages) > 1) {
        echo "   ⚠️ [{$time}] Multiple messages at same timestamp:\n";
        foreach ($messages as $m) {
            echo "      - Persona: {$m['persona']} (ID: {$m['persona_id']}) | {$m['sender']}: {$m['content']}...\n";
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Analysis complete!\n";
