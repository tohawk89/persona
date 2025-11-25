<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot API Token
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Telegram Bot API Token that will be used to
    | authenticate your bot with the Telegram API. You should store this
    | token in your environment file.
    |
    */

    'bots' => [
        'default' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', 'YOUR-CERTIFICATE-PATH'),
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
            'commands' => [
                // Register your bot commands here
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bot Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the bots you wish to use as
    | your default bot for regular use.
    |
    */

    'default' => env('TELEGRAM_BOT_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Requests
    |--------------------------------------------------------------------------
    |
    | When set to true, All the requests would be made non-blocking (Async).
    |
    */

    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Handler
    |--------------------------------------------------------------------------
    |
    | If you'd like to use a custom HTTP Client Handler.
    | Should be an instance of \Telegram\Bot\HttpClients\HttpClientInterface
    |
    */

    'http_client_handler' => null,
];
