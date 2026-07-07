<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Telegram webhook (production). Locally, use `php artisan telegram:poll`.
Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class);
