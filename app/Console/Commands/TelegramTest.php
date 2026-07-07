<?php

namespace App\Console\Commands;

use App\Models\TelegramTemplate;
use App\Telegram\Notifier;
use App\Telegram\TelegramClient;
use Illuminate\Console\Command;

class TelegramTest extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Send a test message to verify Telegram configuration';

    public function handle(TelegramClient $client, Notifier $notifier): int
    {
        if (! $client->isConfigured()) {
            $this->error('Telegram is not configured. Set TELEGRAM_ENABLED=true, TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env.');

            return self::FAILURE;
        }

        $sent = $notifier->send('test', TelegramTemplate::render('test_message'));

        $this->info($sent ? 'Test message sent.' : 'Failed to send — check the telegram_messages log for the error.');

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
