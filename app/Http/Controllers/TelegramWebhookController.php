<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Facades\{Telegram, SmartQueue};
use App\Models\{User, Message};
use App\Jobs\{ProcessChatResponse, ExtractMemoryTags};

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook updates.
     *
     * MULTI-BOT SUPPORT: Routes messages to specific persona based on bot token.
     * UX: Sends immediate typing indicator to prevent "ghost" silence.
     */
    public function webhook(Request $request, string $token): JsonResponse
    {
        // STEP 1: Secret Validation (CRITICAL SECURITY) - Outside try-catch to allow abort
        $webhookSecret = env('TELEGRAM_WEBHOOK_SECRET');
        if ($webhookSecret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $webhookSecret) {
            Log::warning('TelegramWebhookController: Invalid secret token', [
                'ip' => $request->ip(),
                'provided_token' => $request->header('X-Telegram-Bot-Api-Secret-Token'),
            ]);
            abort(403, 'Forbidden: Invalid secret token');
        }

        try {
            // STEP 2: Resolve Persona from Token
            $persona = null;
            $botToken = null;

            // Check 1: Dedicated Bot (Persona-specific token)
            $persona = \App\Models\Persona::where('telegram_bot_token', $token)->first();

            if ($persona) {
                $botToken = $persona->telegram_bot_token;
                Log::info('TelegramWebhookController: Using dedicated bot', [
                    'persona_id' => $persona->id,
                    'persona_name' => $persona->name,
                ]);
            } else {
                // Check 2: System Default Bot
                $systemToken = config('services.telegram.bot_token');

                if ($token === $systemToken) {
                    $botToken = $systemToken;
                    Log::info('TelegramWebhookController: Using system default bot');
                } else {
                    Log::warning('TelegramWebhookController: Unknown bot token', [
                        'token' => substr($token, 0, 10) . '...',
                    ]);
                    return response()->json(['status' => 'ignored', 'reason' => 'Unknown bot token']);
                }
            }

            // STEP 3: Parse Update
            $payload = $request->all();
            $data = Telegram::parseUpdate($payload);
            $message = $payload['message'] ?? null;

            // Check for photo
            $imagePath = null;
            if ($message && isset($message['photo']) && is_array($message['photo'])) {
                // Get the largest photo (last item in array)
                $photo = end($message['photo']);
                $fileId = $photo['file_id'];

                try {
                    // Get file info from Telegram
                    $fileInfo = Telegram::getFile(['file_id' => $fileId]);
                    $filePath = $fileInfo['file_path'] ?? null;

                    if ($filePath) {
                        // Download image from Telegram
                        $botToken = config('services.telegram.bot_token');
                        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

                        $imageData = file_get_contents($fileUrl);

                        if ($imageData) {
                            // Save to temp storage
                            $uuid = \Illuminate\Support\Str::uuid();
                            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
                            $tempPath = storage_path("app/private/temp/{$uuid}.{$extension}");

                            // Ensure temp directory exists
                            if (!is_dir(storage_path('app/private/temp'))) {
                                mkdir(storage_path('app/private/temp'), 0755, true);
                            }

                            file_put_contents($tempPath, $imageData);
                            $imagePath = $tempPath;

                            Log::info('TelegramWebhookController: Downloaded photo', [
                                'file_id' => $fileId,
                                'temp_path' => $tempPath,
                                'size' => strlen($imageData),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('TelegramWebhookController: Failed to download photo', [
                        'file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Get text or caption
            $text = $data['text'] ?? ($message['caption'] ?? null);

            // If photo but no caption, set default text
            if ($imagePath && empty($text)) {
                $text = '[Sent an image]';
            }

            // Log incoming message for debugging
            Log::info('TelegramWebhookController: Received webhook', [
                'chat_id' => $data['chat_id'] ?? null,
                'text' => $text,
                'has_image' => $imagePath !== null,
                'from' => $data['user']['first_name'] ?? null,
            ]);

            // Ignore if no text and no image
            if (empty($text) && !$imagePath) {
                return response()->json(['status' => 'ignored', 'reason' => 'No text or image content']);
            }

            // STEP 3: Security Gate (CRITICAL - Block strangers immediately)
            $chatId = $data['chat_id'];
            $adminId = env('TELEGRAM_ADMIN_ID');

            if ($chatId != $adminId) {
                Log::warning('TelegramWebhookController: Unauthorized chat_id blocked', [
                    'chat_id' => $chatId,
                    'admin_id' => $adminId,
                ]);
                return response()->json(['status' => 'ignored', 'reason' => 'Unauthorized user']);
            }

            // STEP 5: User Resolution
            $user = User::where('telegram_chat_id', $chatId)->first();

            if (!$user) {
                // Create user only if they're the admin
                $user = User::create([
                    'telegram_chat_id' => $chatId,
                    'name' => $data['user']['first_name'] ?? 'Admin',
                    'email' => "telegram_{$chatId}@placeholder.local",
                    'password' => bcrypt(str()->random(32)),
                ]);

                Log::info('TelegramWebhookController: Created new user for admin', [
                    'user_id' => $user->id,
                    'chat_id' => $chatId,
                ]);
            }

            // STEP 6: Final Persona Resolution (for System Bot)
            if (!$persona) {
                // System bot: find user's active persona
                $activePersonas = $user->personas()->where('is_active', true)->get();

                if ($activePersonas->isEmpty()) {
                    Log::warning('TelegramWebhookController: No active persona found for user', [
                        'user_id' => $user->id,
                        'chat_id' => $chatId,
                    ]);

                    Telegram::sendMessage($chatId, 'Please create and activate a persona first via the dashboard.', $botToken);
                    return response()->json(['status' => 'ignored', 'reason' => 'No active persona']);
                }

                // If multiple active personas, prefer the one without a dedicated bot token (uses system bot)
                $persona = $activePersonas->firstWhere('telegram_bot_token', null) ?? $activePersonas->first();

                Log::info('TelegramWebhookController: Using system bot for persona', [
                    'persona_id' => $persona->id,
                    'persona_name' => $persona->name,
                    'total_active' => $activePersonas->count(),
                    'has_dedicated_token' => $persona->telegram_bot_token !== null,
                ]);
            }

            // STEP 7: UX Indicator (CRITICAL - Prevent "Ghost" silence)
            Telegram::sendChatAction($chatId, 'typing', $botToken);

            // STEP 8: Save User Message (for raw logs)
            $userMessage = Message::create([
                'user_id' => $user->id,
                'persona_id' => $persona->id,
                'sender_type' => 'user',
                'content' => $text,
                'image_path' => $imagePath, // Store image path if present
            ]);

            // Update last interaction timestamp
            $user->update(['last_interaction_at' => now()]);

            // STEP 9: Buffer Management (Debounce Pattern)
            if ($imagePath) {
                // Images bypass buffering (process immediately)
                ProcessChatResponse::dispatch($user, $imagePath, $persona, $botToken)->delay(now()->addSeconds(2));
            } else {
                // Text messages: Append to buffer
                $bufferKey = "chat_buffer_{$chatId}_{$persona->id}";
                $existingBuffer = \Illuminate\Support\Facades\Cache::get($bufferKey, '');

                $newBuffer = $existingBuffer
                    ? $existingBuffer . "\n" . $text
                    : $text;

                \Illuminate\Support\Facades\Cache::put($bufferKey, $newBuffer, now()->addSeconds(60));

                Log::info('TelegramWebhookController: Message buffered', [
                    'chat_id' => $chatId,
                    'persona_id' => $persona->id,
                    'buffer_length' => strlen($newBuffer),
                ]);

                // Dispatch delayed job (10 seconds debounce) with persona and bot token
                ProcessChatResponse::dispatch($user, null, $persona, $botToken)->delay(now()->addSeconds(10));
            }

            // Dispatch background job to extract memories (every 10th message)
            $messageCount = Message::where('user_id', $user->id)->count();
            if ($messageCount % 10 === 0) {
                ExtractMemoryTags::dispatch($user);
            }

            // STEP 8: Response
            return response()->json(['ok' => true, 'status' => 'processing']);

        } catch (\Exception $e) {
            Log::error('TelegramWebhookController: Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            // Always return 200 to Telegram to avoid retries
            return response()->json(['ok' => true, 'error' => 'Internal processing error']);
        }
    }

    /**
     * Set up the Telegram webhook (optional utility endpoint).
     */
    public function setupWebhook(): JsonResponse
    {
        try {
            $webhookUrl = config('services.telegram.webhook_url');

            if (!$webhookUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'TELEGRAM_WEBHOOK_URL not configured in .env',
                ], 500);
            }

            // Call Telegram API to set webhook
            $response = \Illuminate\Support\Facades\Http::post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/setWebhook',
                ['url' => $webhookUrl]
            );

            $result = $response->json();

            Log::info('TelegramWebhookController: Webhook setup', [
                'url' => $webhookUrl,
                'response' => $result,
            ]);

            return response()->json([
                'success' => $result['ok'] ?? false,
                'message' => $result['description'] ?? 'Unknown error',
                'webhook_url' => $webhookUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('TelegramWebhookController: Webhook setup failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
