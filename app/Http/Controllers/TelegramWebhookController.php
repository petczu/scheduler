<?php

namespace App\Http\Controllers;

use App\Telegram\UpdateHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production webhook. Register it with Telegram's setWebhook using a URL that
 * includes the secret path segment (TELEGRAM_WEBHOOK_SECRET). Locally, prefer
 * the `telegram:poll` command instead.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, UpdateHandler $handler): Response
    {
        abort_unless(
            filled(config('services.telegram.webhook_secret'))
                && hash_equals((string) config('services.telegram.webhook_secret'), $secret),
            404,
        );

        $handler->handle($request->all());

        return response('', 200);
    }
}
