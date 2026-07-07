<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Telegram Bot API.
 *
 * @see https://core.telegram.org/bots/api
 */
class TelegramClient
{
    /**
     * Configured enough to talk to the API (token present and enabled).
     * Recipients are authorized subscribers, not a single chat id.
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.telegram.enabled')
            && filled(config('services.telegram.token'));
    }

    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $this->call('sendMessage', $payload);
    }

    /**
     * A one-time keyboard with a single "share phone number" button.
     */
    public function requestContactKeyboard(string $buttonText): array
    {
        return [
            'keyboard' => [[['text' => $buttonText, 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }

    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    /**
     * @return array<int, array<string, mixed>> raw update objects
     */
    public function getUpdates(int $offset): array
    {
        return data_get($this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => 0,
            'allowed_updates' => ['message'],
        ]), 'result', []);
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = ['url' => $url, 'allowed_updates' => json_encode(['message'])];

        if (filled($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->call('setWebhook', $payload);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => 'false']);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', []);
    }

    private function call(string $method, array $payload): array
    {
        $token = config('services.telegram.token');

        $response = Http::timeout(20)->asForm()->post("https://api.telegram.org/bot{$token}/{$method}", $payload);

        if ($response->failed() || ! $response->json('ok')) {
            throw new RuntimeException("Telegram {$method} error: ".mb_substr($response->body(), 0, 500));
        }

        return $response->json();
    }
}
