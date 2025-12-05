<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setToken(string $token)
 * @method static bool sendMessage(string|int $chatId, string $message, ?string $botToken = null, array $options = [])
 * @method static bool sendStreamingMessage(string|int $chatId, string $message, ?string $botToken = null)
 * @method static bool sendPhoto(string|int $chatId, string $photo, ?string $caption = null, ?string $botToken = null, array $options = [])
 * @method static bool sendVoice(string|int $chatId, string $voice, ?string $botToken = null, array $options = [])
 * @method static bool sendChatAction(string|int $chatId, string $action = 'typing', ?string $botToken = null)
 * @method static array parseUpdate(array $update)
 *
 * @see \App\Services\TelegramService
 */
class Telegram extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\TelegramService::class;
    }
}
