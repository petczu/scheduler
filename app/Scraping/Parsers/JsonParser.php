<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\ParsedSlot;
use Carbon\CarbonImmutable;

/**
 * Parser for JSON availability endpoints, driven by parser_config.
 *
 * Two shapes are supported.
 *
 * A) Rooms map/list, each room carrying its own slots (Escape Hunt):
 *
 *   {
 *     "rooms_path": "game_list",       // path to the room map/list
 *     "room_name_key": "name",         // room title within each room object
 *     "slots_key": "times",            // slot list within each room object
 *     "datetime_key": "bookTime",      // ISO datetime within each slot
 *     "busy_key": "booked",            // slot field marking a taken slot
 *     "busy_value": true               // value meaning "busy" (default true)
 *   }
 *
 * B) Flat slot list for a single room, date supplied by the request
 *    ({today} in the URL), time in a field (Blackout):
 *
 *   {
 *     "slots_path": "availableHours",  // flat slot array
 *     "time_key": "start",             // "HH:MM" (24h) within each slot
 *     "room_name_path": "availablePrices.0.room.title" // optional label
 *   }
 *
 * Endpoints that only return AVAILABLE slots (Blackout) leave busy slots
 * absent; those scans record availability, and occupancy needs the full
 * grid (learned over time) to be computed.
 */
class JsonParser implements SlotParser
{
    public function parse(string $html, ScanSource $source): array
    {
        $config = $source->parser_config ?? [];
        $data = json_decode($html, true);

        if (! is_array($data)) {
            return [];
        }

        return ! blank($config['slots_path'] ?? null)
            ? $this->parseFlat($data, $config, $source)
            : $this->parseRooms($data, $config, $source);
    }

    /**
     * @return ParsedSlot[]
     */
    protected function parseRooms(array $data, array $config, ScanSource $source): array
    {
        $timezone = $source->venue->timezone;
        $rooms = data_get($data, $config['rooms_path'] ?? 'rooms', []);
        $slots = [];

        foreach ((array) $rooms as $room) {
            $label = data_get($room, $config['room_name_key'] ?? 'name');

            foreach ((array) data_get($room, $config['slots_key'] ?? 'slots', []) as $slot) {
                $raw = data_get($slot, $config['datetime_key'] ?? 'datetime');

                if (blank($raw)) {
                    continue;
                }

                try {
                    $slotAt = CarbonImmutable::parse($raw)->setTimezone($timezone);
                } catch (\Throwable) {
                    continue;
                }

                $busy = data_get($slot, $config['busy_key'] ?? 'booked') == ($config['busy_value'] ?? true);
                $labelString = is_string($label) ? mb_substr($label, 0, 255) : null;

                $slots[] = new ParsedSlot(
                    $slotAt,
                    $busy ? SlotSnapshot::STATUS_SOLD_OUT : SlotSnapshot::STATUS_AVAILABLE,
                    $labelString,
                    $labelString,
                );
            }
        }

        return $slots;
    }

    /**
     * @return ParsedSlot[]
     */
    protected function parseFlat(array $data, array $config, ScanSource $source): array
    {
        $timezone = $source->venue->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $timeKey = $config['time_key'] ?? 'time';

        $label = ! blank($config['room_name_path'] ?? null)
            ? data_get($data, $config['room_name_path'])
            : null;
        $labelString = is_string($label) ? mb_substr($label, 0, 255) : null;

        $slots = [];

        foreach ((array) data_get($data, $config['slots_path'], []) as $slot) {
            $time = data_get($slot, $timeKey);

            if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
                continue;
            }

            $busy = ! blank($config['busy_key'] ?? null)
                && data_get($slot, $config['busy_key']) == ($config['busy_value'] ?? true);

            $slots[] = new ParsedSlot(
                $today->setTime((int) $m[1], (int) $m[2]),
                $busy ? SlotSnapshot::STATUS_SOLD_OUT : SlotSnapshot::STATUS_AVAILABLE,
                $time,
                $labelString,
            );
        }

        return $slots;
    }
}
