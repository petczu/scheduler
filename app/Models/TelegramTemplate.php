<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Editable message templates. Each has a stable key, a body with {placeholder}
 * tokens, and an admin-editable description/placeholder hint. render() pulls
 * the body from the DB (falling back to the built-in default) and substitutes
 * placeholders.
 */
class TelegramTemplate extends Model
{
    protected $fillable = ['key', 'description', 'body'];

    /** @var array<string, string>|null in-request cache of key => body */
    protected static ?array $cache = null;

    public static function render(string $key, array $vars = []): string
    {
        $body = static::bodyFor($key);

        $replacements = [];
        foreach ($vars as $name => $value) {
            $replacements['{'.$name.'}'] = (string) $value;
        }

        return strtr($body, $replacements);
    }

    protected static function bodyFor(string $key): string
    {
        if (static::$cache === null) {
            static::$cache = static::query()->pluck('body', 'key')->all();
        }

        return static::$cache[$key]
            ?? (static::defaults()[$key]['body'] ?? '');
    }

    public static function forgetCache(): void
    {
        static::$cache = null;
    }

    /**
     * Built-in defaults. Keys are stable; body is the fallback text and the
     * value seeded into the DB; placeholders documents the available tokens.
     *
     * @return array<string, array{description: string, placeholders: string, body: string}>
     */
    public static function defaults(): array
    {
        return [
            'digest_morning_header' => [
                'description' => 'Morning digest header',
                'placeholders' => '{date}',
                'body' => "🌅 <b>Morning digest — {date}</b>",
            ],
            'digest_evening_header' => [
                'description' => 'Evening digest header',
                'placeholders' => '{date}',
                'body' => "🌆 <b>Evening digest — {date}</b>",
            ],
            'digest_group_ours' => [
                'description' => 'Digest: our projects section title',
                'placeholders' => '',
                'body' => '<b>Our projects</b>',
            ],
            'digest_group_competitors' => [
                'description' => 'Digest: competitors section title',
                'placeholders' => '',
                'body' => '<b>Competitors</b>',
            ],
            'digest_row' => [
                'description' => 'Digest: one venue line',
                'placeholders' => '{venue} {occupancy} {sold} {total} {released_note}',
                'body' => '{venue} — <b>{occupancy}</b> ({sold}/{total}){released_note}',
            ],
            'digest_empty' => [
                'description' => 'Digest: shown when a section has no data',
                'placeholders' => '',
                'body' => '—',
            ],
            'digest_leader' => [
                'description' => 'Digest: leader line',
                'placeholders' => '{venue} {occupancy}',
                'body' => '🏆 Leader today: <b>{venue}</b> ({occupancy}%)',
            ],
            'alert_fake_booking' => [
                'description' => 'Alert: competitor freed a sold-out slot',
                'placeholders' => '{where} {count}',
                'body' => "⚠️ <b>Possible fake booking</b>\n{where}: a sold-out slot became available again ({count}).",
            ],
            'alert_sold_out' => [
                'description' => 'Alert: competitor selling out today',
                'placeholders' => '{where} {occupancy} {sold} {total}',
                'body' => "🔥 <b>Competitor selling out</b>\n{where}: {occupancy}% today ({sold}/{total}).",
            ],
            'alert_scan_failed' => [
                'description' => 'Alert: a source failed to scan twice in a row',
                'placeholders' => '{venue} {source} {error}',
                'body' => "🛠 <b>Source not scanning</b>\n{venue} / {source}\n<code>{error}</code>",
            ],
            'test_message' => [
                'description' => 'Test message (telegram:test)',
                'placeholders' => '',
                'body' => '✅ Schedule Checker is connected to Telegram.',
            ],
            'bot_welcome' => [
                'description' => 'Bot reply: onboarding / ask for phone',
                'placeholders' => '',
                'body' => "👋 Welcome to <b>Schedule Checker</b>.\nTo receive competitor occupancy digests and alerts, please verify your phone number.",
            ],
            'bot_share_button' => [
                'description' => 'Bot: "share phone" button label',
                'placeholders' => '',
                'body' => '📱 Share phone number',
            ],
            'bot_verified' => [
                'description' => 'Bot reply: phone verified / authorized',
                'placeholders' => '',
                'body' => "✅ You're verified. You'll now receive occupancy digests and alerts.\nSend /stop anytime to unsubscribe.",
            ],
            'bot_not_authorized' => [
                'description' => 'Bot reply: phone not on the allow-list',
                'placeholders' => '',
                'body' => '⛔ This phone number is not authorized. Please contact the administrator.',
            ],
            'bot_not_own_contact' => [
                'description' => 'Bot reply: shared someone else\'s contact',
                'placeholders' => '',
                'body' => '⚠️ Please share <b>your own</b> phone number using the button below.',
            ],
            'bot_already_subscribed' => [
                'description' => 'Bot reply: already authorized user sends a message',
                'placeholders' => '',
                'body' => "✅ You're subscribed. Digests arrive twice a day; alerts as they happen.\nSend /stop to unsubscribe.",
            ],
            'bot_unsubscribed' => [
                'description' => 'Bot reply: after /stop',
                'placeholders' => '',
                'body' => '🔕 You have been unsubscribed. Send /start to subscribe again.',
            ],
        ];
    }
}
