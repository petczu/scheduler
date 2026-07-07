<?php

use App\Models\TelegramTemplate;
use App\Telegram\OccupancyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to the built-in default when no row exists', function () {
    expect(TelegramTemplate::render('test_message'))
        ->toBe('✅ Schedule Checker is connected to Telegram.');
});

it('uses the edited body from the database', function () {
    TelegramTemplate::create([
        'key' => 'test_message',
        'body' => 'Custom hello from admin',
    ]);
    TelegramTemplate::forgetCache();

    expect(TelegramTemplate::render('test_message'))->toBe('Custom hello from admin');
});

it('substitutes placeholders', function () {
    TelegramTemplate::create([
        'key' => 'alert_sold_out',
        'body' => '{where} is at {occupancy}% ({sold}/{total})',
    ]);
    TelegramTemplate::forgetCache();

    expect(TelegramTemplate::render('alert_sold_out', [
        'where' => 'Rival / Room X',
        'occupancy' => 90,
        'sold' => 9,
        'total' => 10,
    ]))->toBe('Rival / Room X is at 90% (9/10)');
});

it('applies an edited digest header to the composed digest', function () {
    TelegramTemplate::create([
        'key' => 'digest_morning_header',
        'body' => '☀️ Daily report {date}',
    ]);
    TelegramTemplate::forgetCache();

    expect(app(OccupancyReport::class)->digest('morning'))
        ->toContain('☀️ Daily report');
});
