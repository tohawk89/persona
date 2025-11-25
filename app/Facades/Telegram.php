<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool sendMessage(string|int $chatId, string $message, array $options = [])
 * @method static bool sendStreamingMessage(string|int $chatId, string $message)
 * @method static bool sendPhoto(string|int $chatId, string $photo, ?string $caption = null, array $options = [])
 * @method static bool sendChatAction(string|int $chatId, string $action = 'typing')
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
