<?php

use App\Models\ScanSource;
use App\Models\Venue;
use App\Scraping\Parsers\FeverParser;
use App\Scraping\Parsers\GenericParser;
use App\Scraping\Parsers\JsonParser;
use App\Scraping\Parsers\QgbParser;

function sourceWith(string $strategy, array $config = []): ScanSource
{
    $source = new ScanSource([
        'name' => 'Test',
        'strategy' => $strategy,
        'parse_mode' => 'detect_busy',
        'parser_config' => $config,
    ]);

    $source->setRelation('venue', new Venue(['timezone' => 'Asia/Dubai']));

    return $source;
}

function competitorFixture(string $name): ?string
{
    $path = storage_path("app/private/scans-fixtures/{$name}");

    return file_exists($path) ? file_get_contents($path) : null;
}

it('parses escape hunt availability JSON', function () {
    $json = competitorFixture('escapehunt-availability.json');
    if ($json === null) {
        $this->markTestSkipped('No captured Escape Hunt response.');
    }

    $slots = app(JsonParser::class)->parse($json, sourceWith('json', [
        'rooms_path' => 'game_list',
        'room_name_key' => 'name',
        'slots_key' => 'times',
        'datetime_key' => 'bookTime',
        'busy_key' => 'booked',
    ]));

    expect(count($slots))->toBeGreaterThan(10);

    $labels = collect($slots)->pluck('roomLabel')->unique();
    expect($labels->count())->toBeGreaterThanOrEqual(4)
        ->and($labels->first())->toContain('escape room');

    foreach ($slots as $slot) {
        expect($slot->slotAt->format('Y'))->toBe('2026');
    }
});

it('parses a flat JSON availability list with a separate date (Blackout)', function () {
    $json = competitorFixture('blackout-availability.json');
    if ($json === null) {
        $this->markTestSkipped('No captured Blackout response.');
    }

    $slots = app(JsonParser::class)->parse($json, sourceWith('json', [
        'slots_path' => 'availableHours',
        'time_key' => 'start',
        'room_name_path' => 'availablePrices.0.room.title',
    ]));

    expect(count($slots))->toBe(6);

    $today = now('Asia/Dubai')->format('Y-m-d');
    $byTime = collect($slots)->keyBy(fn ($s) => $s->slotAt->format('H:i'));

    expect($byTime)->toHaveKeys(['13:00', '14:15', '18:00', '20:30', '21:45', '23:00'])
        ->and($byTime['13:00']->status)->toBe('available')
        ->and($byTime['13:00']->slotAt->format('Y-m-d'))->toBe($today)
        ->and($byTime['13:00']->roomLabel)->toBe('Exorcism');
});

it('parses a questa product page (weekly grid + booked map)', function () {
    $html = competitorFixture('questa-product.html');
    if ($html === null) {
        $this->markTestSkipped('No captured Questa page.');
    }

    $slots = app(QgbParser::class)->parse($html, sourceWith('questa'));

    // 11 slots/day x 7 days for Wild West.
    expect(count($slots))->toBeGreaterThanOrEqual(70)
        ->and(collect($slots)->pluck('status')->unique()->all())->toContain('available');
});

it('marks questa slots from the booked map as sold out', function () {
    $today = now('Asia/Dubai')->format('Y-m-d');
    $html = <<<HTML
        <div class="qgb-wrap" data-open='{"mon":["10:15","11:30"],"tue":["10:15","11:30"],"wed":["10:15","11:30"],"thu":["10:15","11:30"],"fri":["10:15","11:30"],"sat":["10:15","11:30"],"sun":["10:15","11:30"]}'></div>
        <script>window.QGBBookedSlots = {"{$today}":["11:30"]} || {};</script>
    HTML;

    $slots = app(QgbParser::class)->parse($html, sourceWith('questa', ['days_ahead' => 1]));

    expect($slots)->toHaveCount(2);

    $byTime = collect($slots)->keyBy(fn ($s) => $s->slotAt->format('H:i'));
    expect($byTime['10:15']->status)->toBe('available')
        ->and($byTime['11:30']->status)->toBe('sold_out');
});

it('parses a fever event page with per-room sessions', function () {
    $html = competitorFixture('fever-event.html');
    if ($html === null) {
        $this->markTestSkipped('No captured Fever page.');
    }

    $slots = app(FeverParser::class)->parse($html, sourceWith('fever'));

    expect(count($slots))->toBeGreaterThan(5);

    $soldOut = collect($slots)->where('status', 'sold_out');
    // The captured page has the 12:00 session with 0 tickets.
    expect($soldOut->count())->toBeGreaterThanOrEqual(1)
        ->and(collect($slots)->pluck('roomLabel')->filter()->unique()->count())->toBeGreaterThanOrEqual(1);
});

it('parses escape house room page (available slots only)', function () {
    $html = competitorFixture('escapehouse-room.html');
    if ($html === null) {
        $this->markTestSkipped('No captured Escape House page.');
    }

    $slots = app(GenericParser::class)->parse($html, sourceWith('generic', [
        'slot_selector' => '.available-time a.selectedDate',
        'date_attr' => 'data-date',
    ]));

    expect(count($slots))->toBeGreaterThanOrEqual(5);

    foreach ($slots as $slot) {
        expect($slot->status)->toBe('available');
    }
});
