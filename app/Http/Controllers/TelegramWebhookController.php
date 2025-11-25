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
     * SECURITY: Only processes messages from TELEGRAM_ADMIN_ID.
     * UX: Sends immediate typing indicator to prevent "ghost" silence.
     */
    public function webhook(Request $request): JsonResponse
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

            // STEP 2: Parse Update
            $data = Telegram::parseUpdate($request->all());

            // Log incoming message for debugging
            Log::info('TelegramWebhookController: Received webhook', [
                'chat_id' => $data['chat_id'] ?? null,
                'text' => $data['text'] ?? null,
                'from' => $data['user']['first_name'] ?? null,
            ]);

            // Ignore if no text message
            if (empty($data['text'])) {
                return response()->json(['status' => 'ignored', 'reason' => 'No text content']);
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

            // STEP 4: User Resolution
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

            // STEP 5: UX Indicator (CRITICAL - Prevent "Ghost" silence)
            Telegram::sendChatAction($chatId, 'typing');

            // STEP 6: Save User Message
            $message = Message::create([
                'user_id' => $user->id,
                'persona_id' => $user->persona?->id,
                'sender_type' => 'user',
                'content' => $data['text'],
            ]);

            // Update last interaction timestamp
            $user->update(['last_interaction_at' => now()]);

            // STEP 7: Async Processing
            ProcessChatResponse::dispatch($user, $message);

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
