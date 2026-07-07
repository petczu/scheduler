<?php

use App\Models\TelegramAllowedPhone;
use App\Models\TelegramMessage;
use App\Models\TelegramSubscriber;
use App\Telegram\Notifier;
use App\Telegram\UpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.token', 'TEST');
    config()->set('services.telegram.chat_id', null);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function contactUpdate(string $chatId, string $userId, string $phone): array
{
    return [
        'update_id' => 1,
        'message' => [
            'chat' => ['id' => $chatId],
            'from' => ['id' => $userId, 'first_name' => 'Pete'],
            'contact' => ['user_id' => $userId, 'phone_number' => $phone],
        ],
    ];
}

it('authorizes a subscriber whose phone is on the allow-list', function () {
    TelegramAllowedPhone::create(['label' => 'Peter', 'phone' => '+971 50 123 4567']);

    app(UpdateHandler::class)->handle(contactUpdate('555', '999', '971501234567'));

    $sub = TelegramSubscriber::where('chat_id', '555')->first();
    expect($sub->authorized)->toBeTrue()
        ->and($sub->phone)->toBe('971501234567');
});

it('stores phone numbers encrypted, not in plaintext', function () {
    TelegramAllowedPhone::create(['label' => 'Peter', 'phone' => '+971 50 123 4567']);
    app(UpdateHandler::class)->handle(contactUpdate('555', '999', '971501234567'));

    // Raw DB rows must not contain the readable number.
    $rawAllowed = DB::table('telegram_allowed_phones')->value('phone');
    $rawSub = DB::table('telegram_subscribers')->value('phone');

    expect($rawAllowed)->not->toContain('971501234567')
        ->and($rawSub)->not->toContain('971501234567')
        // But the model still matches and decrypts.
        ->and(TelegramAllowedPhone::allows('+971-50-123-4567'))->toBeTrue();
});

it('rejects a phone that is not on the allow-list', function () {
    TelegramAllowedPhone::create(['phone' => '+971 50 000 0000']);

    app(UpdateHandler::class)->handle(contactUpdate('555', '999', '971509999999'));

    expect(TelegramSubscriber::where('chat_id', '555')->first()->authorized)->toBeFalse();
});

it('refuses a contact that is not the sender own number', function () {
    TelegramAllowedPhone::create(['phone' => '971501234567']);

    // contact.user_id (777) differs from from.id (999) → someone else's contact.
    $update = contactUpdate('555', '999', '971501234567');
    $update['message']['contact']['user_id'] = '777';

    app(UpdateHandler::class)->handle($update);

    expect(TelegramSubscriber::where('chat_id', '555')->first()->authorized)->toBeFalse();
});

it('broadcasts only to authorized subscribers', function () {
    TelegramSubscriber::create(['chat_id' => 'A', 'authorized' => true]);
    TelegramSubscriber::create(['chat_id' => 'B', 'authorized' => false]);

    $sent = app(Notifier::class)->send('test', 'hi');

    expect($sent)->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r['chat_id'] === 'A');
});

it('skips when there are no authorized subscribers', function () {
    $sent = app(Notifier::class)->send('test', 'hi');

    expect($sent)->toBeFalse()
        ->and(TelegramMessage::where('status', 'skipped')->exists())->toBeTrue();
    Http::assertNothingSent();
});
