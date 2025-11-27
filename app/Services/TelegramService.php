<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bots.default.token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a text message to a Telegram chat.
     *
     * @param string|int $chatId
     * @param string $message
     * @param array $options Additional options (parse_mode, reply_markup, etc.)
     * @return bool Success status
     */
    public function sendMessage(string|int $chatId, string $message, array $options = []): bool
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ], $options);

            Telegram::sendMessage($params);

            Log::info('TelegramService: Message sent', [
                'chat_id' => $chatId,
                'message_length' => strlen($message),
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to send message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a text message with streaming effect (character by character).
     * This simulates typing by sending the message in chunks.
     *
     * @param string|int $chatId
     * @param string $message
     * @return bool Success status
     */
    public function sendStreamingMessage(string|int $chatId, string $message): bool
    {
        try {
            // Send typing action
            $this->sendChatAction($chatId, 'typing');

            // For simplicity, we'll just send the full message after a short delay
            // In a real streaming implementation, you might update a single message
            // or send chunks, but Telegram has rate limits to consider
            sleep(1); // Simulate typing delay

            return $this->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            Log::error('TelegramService: Failed to send streaming message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a photo to a Telegram chat.
     *
     * @param string|int $chatId
     * @param string $photo URL or file_id
     * @param string|null $caption
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendPhoto(string|int $chatId, string $photo, ?string $caption = null, array $options = []): bool
    {
        try {
            // Convert URL to local file path for Telegram upload
            if (filter_var($photo, FILTER_VALIDATE_URL)) {
                // Extract the path from URL
                // MediaLibrary format: /storage/{media_id}/filename.jpg
                // Old format: /storage/generated_images/filename.jpg
                $path = parse_url($photo, PHP_URL_PATH);
                
                // Remove /storage prefix
                $relativePath = str_replace('/storage/', '', $path);
                
                // Try MediaLibrary path first (storage/app/public/{media_id}/filename.jpg)
                $localPath = storage_path('app/public/' . $relativePath);
                
                // If not found, try legacy public_path for old format
                if (!file_exists($localPath)) {
                    $localPath = public_path('storage/' . $relativePath);
                }

                if (file_exists($localPath)) {
                    $photo = \Telegram\Bot\FileUpload\InputFile::create($localPath);
                } else {
                    Log::error('TelegramService: Photo file not found', [
                        'url' => $photo,
                        'tried_paths' => [
                            storage_path('app/public/' . $relativePath),
                            public_path('storage/' . $relativePath),
                        ],
                    ]);
                    return false;
                }
            } elseif (file_exists($photo)) {
                // For local file paths, use InputFile
                $photo = \Telegram\Bot\FileUpload\InputFile::create($photo);
            }
            // Otherwise assume it's a file_id (string from previous upload)

            $params = array_merge([
                'chat_id' => $chatId,
                'photo' => $photo,
            ], $options);

            if ($caption) {
                $params['caption'] = $caption;
                $params['parse_mode'] = 'HTML';
            }

            Telegram::sendPhoto($params);

            Log::info('TelegramService: Photo sent', [
                'chat_id' => $chatId,
                'photo' => is_string($photo) ? substr($photo, 0, 50) : 'InputFile object',
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to send photo', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a voice message (audio file).
     *
     * @param string|int $chatId
     * @param string $voice URL or file_id of the voice file
     * @param array $options Additional Telegram API options
     * @return bool Success status
     */
    public function sendVoice(string|int $chatId, string $voice, array $options = []): bool
    {
        try {
            // Convert URL to local file path for Telegram upload (same logic as sendPhoto)
            if (filter_var($voice, FILTER_VALIDATE_URL)) {
                // Extract the path from URL
                // MediaLibrary format: /storage/{media_id}/filename.mp3
                // Old format: /storage/voice_notes/filename.mp3
                $path = parse_url($voice, PHP_URL_PATH);
                
                // Remove /storage prefix
                $relativePath = str_replace('/storage/', '', $path);
                
                // Try MediaLibrary path first (storage/app/public/{media_id}/filename.mp3)
                $localPath = storage_path('app/public/' . $relativePath);
                
                // If not found, try legacy public_path for old format
                if (!file_exists($localPath)) {
                    $localPath = public_path('storage/' . $relativePath);
                }

                if (file_exists($localPath)) {
                    $voice = \Telegram\Bot\FileUpload\InputFile::create($localPath);
                } else {
                    Log::error('TelegramService: Voice file not found', [
                        'url' => $voice,
                        'tried_paths' => [
                            storage_path('app/public/' . $relativePath),
                            public_path('storage/' . $relativePath),
                        ],
                    ]);
                    return false;
                }
            } elseif (file_exists($voice)) {
                // For local file paths, use InputFile
                $voice = \Telegram\Bot\FileUpload\InputFile::create($voice);
            }
            // Otherwise assume it's a file_id (string from previous upload)

            $params = array_merge([
                'chat_id' => $chatId,
                'voice' => $voice,
            ], $options);

            Telegram::sendVoice($params);

            Log::info('TelegramService: Voice message sent', [
                'chat_id' => $chatId,
                'voice' => is_string($voice) ? substr($voice, 0, 50) : 'InputFile object',
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to send voice', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a chat action (typing, upload_photo, etc.).
     *
     * @param string|int $chatId
     * @param string $action (typing, upload_photo, record_video, etc.)
     * @return bool Success status
     */
    public function sendChatAction(string|int $chatId, string $action = 'typing'): bool
    {
        try {
            Telegram::sendChatAction([
                'chat_id' => $chatId,
                'action' => $action,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to send chat action', [
                'chat_id' => $chatId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Set webhook for receiving updates.
     *
     * @param string $url
     * @return bool Success status
     */
    public function setWebhook(string $url): bool
    {
        try {
            $response = Telegram::setWebhook([
                'url' => $url,
            ]);

            Log::info('TelegramService: Webhook set', [
                'url' => $url,
                'response' => $response,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to set webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove webhook.
     *
     * @return bool Success status
     */
    public function removeWebhook(): bool
    {
        try {
            Telegram::removeWebhook();

            Log::info('TelegramService: Webhook removed');

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to remove webhook', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get bot information.
     *
     * @return array|null Bot info (id, username, first_name, etc.)
     */
    public function getMe(): ?array
    {
        try {
            $response = Telegram::getMe();

            return [
                'id' => $response->getId(),
                'is_bot' => $response->getIsBot(),
                'first_name' => $response->getFirstName(),
                'username' => $response->getUsername(),
            ];
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to get bot info', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get webhook info.
     *
     * @return array|null
     */
    public function getWebhookInfo(): ?array
    {
        try {
            $info = Telegram::getWebhookInfo();

            return $info->toArray();
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to get webhook info', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get updates (polling mode).
     *
     * @param array $params Parameters (offset, limit, timeout)
     * @return array Updates
     */
    public function getUpdates(array $params = []): array
    {
        try {
            $response = Telegram::getUpdates($params);
            return $response;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to get updates', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Send a message and then edit it (useful for simulating real-time updates).
     *
     * @param string|int $chatId
     * @param string $initialMessage
     * @param string $finalMessage
     * @return bool Success status
     */
    public function sendAndEditMessage(string|int $chatId, string $initialMessage, string $finalMessage): bool
    {
        try {
            // Send initial message
            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $initialMessage,
                'parse_mode' => 'HTML',
            ]);

            $messageId = $response->getMessageId();

            // Wait a bit
            sleep(1);

            // Edit the message
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $finalMessage,
                'parse_mode' => 'HTML',
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('TelegramService: Failed to send and edit message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file information from Telegram API.
     *
     * @param array $params Parameters including file_id
     * @return array File info with file_path
     */
    public function getFile(array $params): array
    {
        try {
            $file = Telegram::getFile($params);

            return [
                'file_id' => $file->getFileId(),
                'file_unique_id' => $file->getFileUniqueId(),
                'file_size' => $file->getFileSize(),
                'file_path' => $file->getFilePath(),
            ];
        } catch (\Exception $e) {
            Log::error('TelegramService: Failed to get file info', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Download a file from Telegram servers.
     *
     * @param string $fileId
     * @return string|null File path or null on failure
     */
    public function downloadFile(string $fileId): ?string
    {
        try {
            $file = Telegram::getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            $fileUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";

            $response = Http::get($fileUrl);

            if ($response->successful()) {
                $fileName = basename($filePath);
                $localPath = storage_path("app/telegram/{$fileName}");

                // Ensure directory exists
                if (!is_dir(dirname($localPath))) {
                    mkdir(dirname($localPath), 0755, true);
                }

                file_put_contents($localPath, $response->body());

                Log::info('TelegramService: File downloaded', [
                    'file_id' => $fileId,
                    'local_path' => $localPath,
                ]);

                return $localPath;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('TelegramService: Failed to download file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse incoming webhook update and extract relevant data.
     *
     * @param array $update
     * @return array Parsed data with keys: chat_id, message_id, text, user, etc.
     */
    public function parseUpdate(array $update): array
    {
        $message = $update['message'] ?? null;

        if (!$message) {
            return [];
        }

        return [
            'chat_id' => $message['chat']['id'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'text' => $message['text'] ?? null,
            'user' => [
                'id' => $message['from']['id'] ?? null,
                'first_name' => $message['from']['first_name'] ?? null,
                'last_name' => $message['from']['last_name'] ?? null,
                'username' => $message['from']['username'] ?? null,
            ],
            'date' => $message['date'] ?? null,
            'photo' => $message['photo'] ?? null,
            'document' => $message['document'] ?? null,
        ];
    }
}
