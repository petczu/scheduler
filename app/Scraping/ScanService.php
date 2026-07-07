<?php

namespace App\Scraping;

use App\Models\Room;
use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\Fetchers\Fetcher;
use App\Scraping\Fetchers\HttpFetcher;
use App\Scraping\Fetchers\ScrapflyFetcher;
use App\Scraping\Parsers\BookeoParser;
use App\Scraping\Parsers\FeverParser;
use App\Scraping\Parsers\GenericParser;
use App\Scraping\Parsers\JsonParser;
use App\Scraping\Parsers\QgbParser;
use App\Scraping\Parsers\SlotParser;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanService
{
    public function scan(ScanSource $source): ScanRun
    {
        $run = ScanRun::create([
            'scan_source_id' => $source->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $this->fetcherFor($source)->fetch($source);

            $htmlPath = "scans/{$run->id}.html";
            Storage::put($htmlPath, $result->html);

            $this->assertNotBlocked($result->html);

            $slots = $this->parserFor($source)->parse($result->html, $source);

            $roomIds = [];
            $seen = []; // "roomId|Y-m-d H:i" of slots present in this scan
            foreach ($slots as $slot) {
                $room = $this->resolveRoom($source, $slot->roomLabel);
                $roomIds[$room->id] = true;
                $seen[$room->id.'|'.$slot->slotAt->format('Y-m-d H:i')] = true;

                $run->slotSnapshots()->create([
                    'room_id' => $room->id,
                    'slot_at' => $slot->slotAt,
                    'status' => $slot->status,
                    'raw_label' => $slot->rawLabel,
                    'scanned_at' => $run->started_at,
                ]);
            }

            $inferred = $source->available_only
                ? $this->inferBookings($source, $run, $seen)
                : 0;

            $run->update([
                'status' => 'success',
                'fetcher' => $result->fetcher,
                'credits_cost' => $result->creditsCost,
                'slots_found' => count($slots) + $inferred,
                'rooms_found' => count($roomIds),
                'raw_html_path' => $htmlPath,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);
        }

        return $run;
    }

    /**
     * For "available-only" sources (Escape House, Blackout) a booked slot
     * simply disappears from the listing. So a slot that was available in the
     * previous scan and is missing now — while its start time is still in the
     * future — is a new booking. A slot that vanished because its time already
     * passed is NOT a booking and is ignored.
     *
     * We record a synthetic sold_out snapshot for each such slot so occupancy
     * and the "released" (fake-booking) logic work exactly as for sites that
     * mark SOLD OUT explicitly.
     *
     * @param  array<string, true>  $seen  keys "roomId|Y-m-d H:i" present now
     * @return int number of inferred sold_out snapshots
     */
    protected function inferBookings(ScanSource $source, ScanRun $run, array $seen): int
    {
        $previous = $source->scanRuns()
            ->where('id', '<', $run->id)
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if ($previous === null) {
            return 0;
        }

        // slot_at is stored as venue-local wall time, so "now" for the
        // past/future check must be taken in the venue's timezone too.
        $venueNow = \Carbon\CarbonImmutable::now($source->venue->timezone);

        // Latest status per slot in the previous scan.
        $previousSlots = $previous->slotSnapshots()
            ->orderBy('id')
            ->get(['room_id', 'slot_at', 'status']);

        $inferred = 0;

        foreach ($previousSlots as $prev) {
            if ($prev->status !== SlotSnapshot::STATUS_AVAILABLE) {
                continue;
            }

            $key = $prev->room_id.'|'.$prev->slot_at->format('Y-m-d H:i');

            // Still listed → not booked.
            if (isset($seen[$key])) {
                continue;
            }

            $slotLocal = \Carbon\CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $prev->slot_at->format('Y-m-d H:i:s'),
                $source->venue->timezone,
            );

            // Gone because the time already passed → not a booking.
            if ($slotLocal->lessThanOrEqualTo($venueNow)) {
                continue;
            }

            $run->slotSnapshots()->create([
                'room_id' => $prev->room_id,
                'slot_at' => $prev->slot_at,
                'status' => SlotSnapshot::STATUS_SOLD_OUT,
                'raw_label' => 'inferred: slot disappeared',
                'scanned_at' => $run->started_at,
            ]);

            $inferred++;
        }

        return $inferred;
    }

    /**
     * Map a card title to a room of this source. Unknown titles create the
     * room automatically, so adding a location source discovers its games
     * on the first scan.
     */
    protected function resolveRoom(ScanSource $source, ?string $roomLabel): Room
    {
        $rooms = $source->rooms()->get();

        if ($roomLabel === null) {
            return $rooms->first() ?? $source->rooms()->create([
                'venue_id' => $source->venue_id,
                'name' => $source->name,
            ]);
        }

        $match = $rooms->first(
            fn (Room $room) => mb_strtolower($room->matchLabel()) === mb_strtolower($roomLabel)
        );

        return $match ?? $source->rooms()->create([
            'venue_id' => $source->venue_id,
            'name' => $roomLabel,
            'match_label' => $roomLabel,
        ]);
    }

    /**
     * A "successful" fetch may still be a block/CAPTCHA page. Fail loudly
     * instead of recording a misleading success with 0 slots.
     */
    protected function assertNotBlocked(string $html): void
    {
        $signatures = [
            'unauthorized IP address',
            'IP masquerading',
            'verify you\'re not a robot',
            'verify you are not a robot',
            'cf-challenge',
            'grecaptcha',
            'hcaptcha',
        ];

        foreach ($signatures as $signature) {
            if (mb_stripos($html, $signature) !== false) {
                throw new \RuntimeException("Blocked by target site (matched \"{$signature}\"). Try residential proxies or a different fetcher.");
            }
        }
    }

    protected function fetcherFor(ScanSource $source): Fetcher
    {
        return match ($source->fetcher) {
            'scrapfly' => app(ScrapflyFetcher::class),
            default => app(HttpFetcher::class),
        };
    }

    protected function parserFor(ScanSource $source): SlotParser
    {
        return match ($source->strategy) {
            'bookeo' => app(BookeoParser::class),
            'json' => app(JsonParser::class),
            'questa' => app(QgbParser::class),
            'fever' => app(FeverParser::class),
            default => app(GenericParser::class),
        };
    }
}
