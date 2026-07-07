<?php

namespace App\Console\Commands;

use App\Telegram\TelegramClient;
use App\Telegram\UpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pulls new updates from Telegram (long-polling style, single pass) and
 * processes them. Works without a public URL — suitable for local/Herd.
 * For production you can use the webhook route instead.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Fetch and process incoming Telegram updates (onboarding, phone verification)';

    public function handle(TelegramClient $client, UpdateHandler $handler): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Telegram is not configured; nothing to poll.');

            return self::SUCCESS;
        }

        $offset = (int) Cache::get('telegram:update_offset', 0);
        $updates = $client->getUpdates($offset);
        $processed = 0;

        foreach ($updates as $update) {
            $handler->handle($update);
            $offset = ((int) ($update['update_id'] ?? $offset)) + 1;
            $processed++;
        }

        Cache::put('telegram:update_offset', $offset);
        $this->info("Processed {$processed} update(s).");

        return self::SUCCESS;
    }
}
