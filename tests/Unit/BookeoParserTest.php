<?php

use App\Models\ScanSource;
use App\Models\Venue;
use App\Scraping\Parsers\BookeoParser;

function bookeoSource(): ScanSource
{
    $source = new ScanSource([
        'name' => 'Bookeo location page',
        'strategy' => 'bookeo',
        'parse_mode' => 'detect_busy',
    ]);

    $source->setRelation('venue', new Venue(['timezone' => 'Asia/Dubai']));

    return $source;
}

it('parses real bookeo slot buttons and tags them with the card title', function () {
    $html = <<<'HTML'
        <div class="cbtce_boxes">
            <div class="cbtce_box">
                <div class="cbtce_boxPane">
                    <div class="cbtce_boxTitle">Orient Express</div>
                </div>
                <div class="cbtce_boxButtons singledate">
                    <button class="cbtce_boxSlot  cbtce_boxSlot_actionable"
                            onclick="cbtce_submit('3254JMLTCF-32547TUTMJ-2026-07-07');utils_cancelEventBubbling()">
                        <div class="cbtce_boxSlotTime">10:45 AM</div>
                    </button>
                    <button class="cbtce_boxSlot cbtce_boxSlot_eventLabel_full " disabled="disabled">
                        <div class="cbtce_boxSlotTime">4:45 PM</div>
                        <div class="cbtce_actionInfo">SOLD OUT</div>
                    </button>
                </div>
            </div>
            <div class="cbtce_box">
                <div class="cbtce_boxPane">
                    <div class="cbtce_boxTitle">Zodiac Killer</div>
                </div>
                <div class="cbtce_boxButtons singledate">
                    <button class="cbtce_boxSlot  cbtce_boxSlot_actionable"
                            onclick="cbtce_submit('3254ABCDEF-3254XYZ123-2026-07-07');utils_cancelEventBubbling()">
                        <div class="cbtce_boxSlotTime">6:15 PM</div>
                    </button>
                </div>
            </div>
        </div>
    HTML;

    $slots = app(BookeoParser::class)->parse($html, bookeoSource());

    expect($slots)->toHaveCount(3);

    $byKey = collect($slots)->keyBy(fn ($s) => $s->roomLabel.' '.$s->slotAt->format('H:i'));

    expect($byKey['Orient Express 10:45']->status)->toBe('available')
        ->and($byKey['Orient Express 16:45']->status)->toBe('sold_out')
        ->and($byKey['Zodiac Killer 18:15']->status)->toBe('available')
        // Sold-out slot has no onclick — date comes from sibling submit payloads.
        ->and($byKey['Orient Express 16:45']->slotAt->format('Y-m-d'))->toBe('2026-07-07');
});

it('parses a captured real bookeo page when available', function () {
    $fixture = storage_path('app/private/scans-fixtures/bookeo-orient-express.html');

    if (! file_exists($fixture)) {
        $this->markTestSkipped('No captured Bookeo page on this machine.');
    }

    $slots = app(BookeoParser::class)->parse(file_get_contents($fixture), bookeoSource());

    expect(count($slots))->toBeGreaterThanOrEqual(8);

    $soldOut = collect($slots)->where('status', 'sold_out');

    expect($soldOut)->toHaveCount(1)
        ->and($soldOut->first()->slotAt->format('H:i'))->toBe('16:45')
        ->and($soldOut->first()->roomLabel)->toBe('Orient Express');
});

it('parses the fixedEventSlot Bookeo widget (Escape The Room)', function () {
    $html = <<<'HTML'
        <div class="fixedEventSlots">
            <button class="fixedEventSlot noproviders available" onclick="cbTimeFixed_onSlotClick('2026-07-07','14:45','15:45',23014565);return false">
                <div class="time">2:45 PM</div>
                <div class="status"><div class="eventLabel_available">Available: 7</div></div>
            </button>
            <button class="fixedEventSlot noproviders full" onclick="cbTimeFixed_onSlotClick('2026-07-07','16:15','17:15',23014566);return false">
                <div class="time">4:15 PM</div>
                <div class="status"><div class="eventLabel_full">Fully booked</div></div>
            </button>
        </div>
    HTML;

    $slots = app(BookeoParser::class)->parse($html, bookeoSource());

    expect($slots)->toHaveCount(2);

    $byTime = collect($slots)->keyBy(fn ($s) => $s->slotAt->format('H:i'));
    expect($byTime['14:45']->status)->toBe('available')
        ->and($byTime['16:15']->status)->toBe('sold_out')
        ->and($byTime['14:45']->slotAt->format('Y-m-d'))->toBe('2026-07-07');
});

it('parses a captured fixedEventSlot page when available', function () {
    $fixture = storage_path('app/private/scans-fixtures/bookeo-fixedevent-etr.html');

    if (! file_exists($fixture)) {
        $this->markTestSkipped('No captured Escape The Room page.');
    }

    $slots = app(BookeoParser::class)->parse(file_get_contents($fixture), bookeoSource());

    expect(count($slots))->toBeGreaterThanOrEqual(6);
});

it('ignores elements without a parsable time', function () {
    $html = <<<'HTML'
        <div>
            <button class="cbtce_boxSlot cbtce_boxSlot_actionable">Book now</button>
            <button class="cbtce_boxSlot cbtce_boxSlot_actionable"
                    onclick="cbtce_submit('X-Y-2026-07-08')">
                <div class="cbtce_boxSlotTime">6:30 PM</div>
            </button>
        </div>
    HTML;

    $slots = app(BookeoParser::class)->parse($html, bookeoSource());

    expect($slots)->toHaveCount(1)
        ->and($slots[0]->slotAt->format('Y-m-d H:i'))->toBe('2026-07-08 18:30');
});
