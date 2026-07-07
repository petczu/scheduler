<?php

use App\Models\TelegramAllowedPhone;
use App\Models\TelegramSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.token', 'TEST');
    config()->set('services.telegram.webhook_secret', 's3cr3t');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function webhookUpdate(): array
{
    return [
        'update_id' => 10,
        'message' => [
            'chat' => ['id' => '4242'],
            'from' => ['id' => '4242', 'first_name' => 'Pete'],
            'contact' => ['user_id' => '4242', 'phone_number' => '971501234567'],
        ],
    ];
}

it('processes an update posted to the webhook with the correct secret', function () {
    TelegramAllowedPhone::create(['phone' => '971501234567']);

    $this->postJson('/telegram/webhook/s3cr3t', webhookUpdate())
        ->assertOk();

    expect(TelegramSubscriber::where('chat_id', '4242')->first()->authorized)->toBeTrue();
});

it('rejects a wrong webhook secret', function () {
    $this->postJson('/telegram/webhook/wrong', webhookUpdate())
        ->assertNotFound();

    expect(TelegramSubscriber::count())->toBe(0);
});

it('404s when no webhook secret is configured', function () {
    config()->set('services.telegram.webhook_secret', null);

    $this->postJson('/telegram/webhook/s3cr3t', webhookUpdate())
        ->assertNotFound();
});
