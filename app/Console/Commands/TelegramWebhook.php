<?php

namespace App\Console\Commands;

use App\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * Register / remove / inspect the Telegram webhook.
 *
 *   php artisan telegram:webhook set        # register at APP_URL + secret path
 *   php artisan telegram:webhook set https://custom-domain
 *   php artisan telegram:webhook info
 *   php artisan telegram:webhook delete     # switch back to polling
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {action=info : set|delete|info} {baseUrl?}';

    protected $description = 'Manage the Telegram webhook';

    public function handle(TelegramClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Telegram is not configured. Set TELEGRAM_ENABLED=true and TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'set' => $this->set($client),
            'delete' => $this->delete($client),
            default => $this->info_($client),
        };
    }

    private function set(TelegramClient $client): int
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '') {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET in .env first (a long random string).');

            return self::FAILURE;
        }

        $base = rtrim($this->argument('baseUrl') ?: (string) config('app.url'), '/');

        // Telegram requires HTTPS. Upgrade http:// automatically.
        $base = preg_replace('#^http://#i', 'https://', $base);
        if (! str_starts_with($base, 'https://')) {
            $base = 'https://'.ltrim($base, '/');
        }

        $host = parse_url($base, PHP_URL_HOST) ?: '';
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            $this->error("\"{$base}\" is not a public HTTPS host Telegram can reach.");
            $this->line('Set APP_URL to your public domain in .env, or pass it explicitly:');
            $this->line('  php artisan telegram:webhook set https://your-domain.com');

            return self::FAILURE;
        }

        $url = "{$base}/telegram/webhook/{$secret}";

        $client->setWebhook($url, $secret);
        $this->info("Webhook set to: {$url}");
        $this->line('Remember: polling (telegram:poll) is auto-disabled while a webhook secret is set.');

        return self::SUCCESS;
    }

    private function delete(TelegramClient $client): int
    {
        $client->deleteWebhook();
        $this->info('Webhook deleted. Clear TELEGRAM_WEBHOOK_SECRET to resume polling.');

        return self::SUCCESS;
    }

    private function info_(TelegramClient $client): int
    {
        $info = $client->getWebhookInfo()['result'] ?? [];

        $this->line('URL:              '.($info['url'] ?: '(none — polling mode)'));
        $this->line('Pending updates:  '.($info['pending_update_count'] ?? 0));
        if (! empty($info['last_error_message'])) {
            $this->warn('Last error:       '.$info['last_error_message']);
        }

        return self::SUCCESS;
    }
}
