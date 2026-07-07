<?php

namespace App\Telegram;

use App\Models\TelegramMessage;
use App\Models\TelegramSubscriber;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Broadcasts a message to every authorized subscriber and records it once in
 * telegram_messages. An optional signature deduplicates alerts: a message
 * with a signature that was already logged is not sent again.
 */
class Notifier
{
    public function __construct(
        private readonly TelegramClient $client,
    ) {}

    /**
     * @return bool whether the message was delivered to at least one recipient
     */
    public function send(string $kind, string $text, ?string $signature = null): bool
    {
        if ($signature !== null && TelegramMessage::where('signature', $signature)->exists()) {
            return false;
        }

        try {
            $message = TelegramMessage::create([
                'kind' => $kind,
                'signature' => $signature,
                'text' => $text,
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            // Unique-constraint race on signature — already handled elsewhere.
            return false;
        }

        if (! $this->client->isConfigured()) {
            $message->update(['status' => 'skipped', 'error' => 'Telegram not configured']);

            return false;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            $message->update(['status' => 'skipped', 'error' => 'No authorized subscribers']);

            return false;
        }

        $delivered = 0;
        $errors = [];

        foreach ($recipients as $chatId) {
            try {
                $this->client->sendMessage($chatId, $text);
                $delivered++;
            } catch (Throwable $e) {
                $errors[] = "chat {$chatId}: ".$e->getMessage();
            }
        }

        $message->update([
            'status' => $delivered > 0 ? 'sent' : 'failed',
            'error' => $errors === [] ? null : mb_substr(implode('; ', $errors), 0, 2000),
        ]);

        return $delivered > 0;
    }

    /**
     * Send a one-off message to specific chat ids (e.g. a template preview).
     * Logged as its own row; no dedup.
     *
     * @param  string[]  $chatIds
     * @return bool whether it reached at least one chat
     */
    public function sendToChats(array $chatIds, string $kind, string $text): bool
    {
        $message = TelegramMessage::create([
            'kind' => $kind,
            'text' => $text,
            'status' => 'pending',
        ]);

        if (! $this->client->isConfigured()) {
            $message->update(['status' => 'skipped', 'error' => 'Telegram not configured']);

            return false;
        }

        $chatIds = array_values(array_unique($chatIds));
        if ($chatIds === []) {
            $message->update(['status' => 'skipped', 'error' => 'No recipient selected']);

            return false;
        }

        $delivered = 0;
        $errors = [];
        foreach ($chatIds as $chatId) {
            try {
                $this->client->sendMessage($chatId, $text);
                $delivered++;
            } catch (Throwable $e) {
                $errors[] = "chat {$chatId}: ".$e->getMessage();
            }
        }

        $message->update([
            'status' => $delivered > 0 ? 'sent' : 'failed',
            'error' => $errors === [] ? null : mb_substr(implode('; ', $errors), 0, 2000),
        ]);

        return $delivered > 0;
    }

    /**
     * @return string[] chat ids of authorized subscribers, plus an optional
     *                  fallback chat id from config.
     */
    private function recipients(): array
    {
        $chatIds = TelegramSubscriber::authorized()->pluck('chat_id')->all();

        $fallback = config('services.telegram.chat_id');
        if (filled($fallback)) {
            $chatIds[] = (string) $fallback;
        }

        return array_values(array_unique($chatIds));
    }
}
