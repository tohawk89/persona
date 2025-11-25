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
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Parse the incoming Telegram update
            $data = Telegram::parseUpdate($request->all());

            // Log incoming message for debugging
            Log::info('TelegramWebhookController: Received webhook', [
                'chat_id' => $data['chat_id'] ?? null,
                'text' => $data['text'] ?? null,
            ]);

            // Ignore if no text message
            if (empty($data['text'])) {
                return response()->json(['ok' => true, 'message' => 'No text content']);
            }

            // Find or create user by Telegram chat ID
            $user = User::firstOrCreate(
                ['telegram_chat_id' => $data['chat_id']],
                [
                    'name' => $data['user']['first_name'] ?? 'User',
                    'email' => "telegram_{$data['chat_id']}@placeholder.local",
                    'password' => bcrypt(str()->random(32)), // Random password
                ]
            );

            // Update user's last interaction timestamp (triggers SmartQueue logic)
            SmartQueue::updateUserInteraction($user);

            // Save incoming user message to database
            $message = Message::create([
                'user_id' => $user->id,
                'persona_id' => $user->persona?->id,
                'sender_type' => 'user',
                'content' => $data['text'],
            ]);

            // Dispatch job to process chat response (async)
            ProcessChatResponse::dispatch($user, $message);

            // Dispatch background job to extract memories (every 10th message)
            $messageCount = Message::where('user_id', $user->id)->count();
            if ($messageCount % 10 === 0) {
                ExtractMemoryTags::dispatch($user);
            }

            return response()->json(['ok' => true]);

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
