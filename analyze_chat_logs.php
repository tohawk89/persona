<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Message, Persona, User};
use Illuminate\Support\Facades\DB;

echo "=== DETAILED CHAT LOG ANALYSIS ===\n\n";

$hana = Persona::find(1);
$fariza = Persona::find(3);

echo "📊 Comparing chat logs between Hana (ID: 1) and Fariza (ID: 3)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get all messages ordered by time
$allMessages = Message::whereIn('persona_id', [1, 3])
    ->orderBy('created_at', 'asc')
    ->get();

echo "Total messages: " . $allMessages->count() . "\n";
echo "Hana messages: " . Message::where('persona_id', 1)->count() . "\n";
echo "Fariza messages: " . Message::where('persona_id', 3)->count() . "\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 CHRONOLOGICAL CHAT LOG (Last 50 messages):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$recent = Message::whereIn('persona_id', [1, 3])
    ->orderBy('created_at', 'desc')
    ->take(50)
    ->get()
    ->reverse();

$previousPersona = null;
$previousSender = null;
$conversationSwitches = [];

foreach ($recent as $msg) {
    $persona = $msg->persona_id == 1 ? 'Hana' : 'Fariza';
    $sender = $msg->sender_type === 'user' ? '👨 USER' : '🤖 BOT';
    $time = $msg->created_at->format('H:i:s');
    $content = substr($msg->content, 0, 80);
    if (strlen($msg->content) > 80) $content .= '...';

    // Detect suspicious patterns
    $suspicious = '';

    // Pattern 1: Different personas responding in quick succession
    if ($previousPersona && $previousPersona !== $persona && $msg->sender_type === 'bot') {
        $timeDiff = $msg->created_at->diffInSeconds($recent->where('id', '<', $msg->id)->last()?->created_at ?? $msg->created_at);
        if ($timeDiff < 60) {
            $suspicious = ' ⚠️ PERSONA SWITCH (within ' . $timeDiff . 's)';
            $conversationSwitches[] = [
                'from' => $previousPersona,
                'to' => $persona,
                'time' => $time,
                'content' => $content,
            ];
        }
    }

    // Pattern 2: User message to one persona, bot response from another
    if ($previousSender === 'user' && $msg->sender_type === 'bot' && $previousPersona !== $persona) {
        $suspicious .= ' 🚨 CROSS-RESPONSE!';
    }

    $color = $persona === 'Hana' ? '' : '🔵 ';
    echo "[{$time}] {$color}[{$persona}] {$sender}: {$content}{$suspicious}\n";

    $previousPersona = $persona;
    $previousSender = $msg->sender_type;
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 ANALYSIS SUMMARY:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if (empty($conversationSwitches)) {
    echo "✅ No suspicious persona switches detected in recent messages.\n";
} else {
    echo "⚠️ Found " . count($conversationSwitches) . " persona switches:\n\n";
    foreach ($conversationSwitches as $switch) {
        echo "   [{$switch['time']}] {$switch['from']} → {$switch['to']}\n";
        echo "   Message: {$switch['content']}\n\n";
    }
}

// Check for interleaved conversations
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 CHECKING FOR SPECIFIC CROSS-CONTAMINATION:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Look for pattern: USER message to Hana, then Fariza responds
$userMessages = Message::where('persona_id', 1)
    ->where('sender_type', 'user')
    ->orderBy('created_at', 'desc')
    ->take(20)
    ->get();

foreach ($userMessages as $userMsg) {
    // Check if there's a Fariza response shortly after
    $farizaResponse = Message::where('persona_id', 3)
        ->where('sender_type', 'bot')
        ->where('created_at', '>', $userMsg->created_at)
        ->where('created_at', '<', $userMsg->created_at->copy()->addMinutes(2))
        ->first();

    if ($farizaResponse) {
        // Check if Hana also responded
        $hanaResponse = Message::where('persona_id', 1)
            ->where('sender_type', 'bot')
            ->where('created_at', '>', $userMsg->created_at)
            ->where('created_at', '<', $userMsg->created_at->copy()->addMinutes(2))
            ->first();

        echo "🚨 FOUND CROSS-CONTAMINATION:\n";
        echo "   [{$userMsg->created_at->format('H:i:s')}] USER to Hana: " . substr($userMsg->content, 0, 60) . "...\n";
        echo "   [{$farizaResponse->created_at->format('H:i:s')}] Fariza responded: " . substr($farizaResponse->content, 0, 60) . "...\n";
        if ($hanaResponse) {
            echo "   [{$hanaResponse->created_at->format('H:i:s')}] Hana also responded: " . substr($hanaResponse->content, 0, 60) . "...\n";
        }
        echo "\n";
    }
}

// Check webhook routing
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 WEBHOOK CONFIGURATION:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Hana Bot Token:   " . substr($hana->telegram_bot_token ?? 'None', 0, 20) . "...\n";
echo "Fariza Bot Token: " . substr($fariza->telegram_bot_token ?? 'None', 0, 20) . "...\n\n";

echo "Expected Webhook URLs:\n";
if ($hana->telegram_bot_token) {
    echo "  Hana:   " . config('app.url') . "/telegram/webhook/" . $hana->telegram_bot_token . "\n";
}
if ($fariza->telegram_bot_token) {
    echo "  Fariza: " . config('app.url') . "/telegram/webhook/" . $fariza->telegram_bot_token . "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Analysis complete!\n";
