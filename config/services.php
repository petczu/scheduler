<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'scrapfly' => [
        'key' => env('SCRAPFLY_KEY'),
        'country' => env('SCRAPFLY_COUNTRY', 'ae'),
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Optional fallback recipient; normally messages go to authorized subscribers.
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        // Competitor room at/above this today-occupancy % triggers a "selling out" alert.
        'sold_out_threshold' => (int) env('TELEGRAM_SOLD_OUT_THRESHOLD', 80),
        // Alert if no successful scan for this many minutes (scanning stalled).
        'stall_minutes' => (int) env('TELEGRAM_STALL_MINUTES', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
