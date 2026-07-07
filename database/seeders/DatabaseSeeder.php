<?php

namespace Database\Seeders;

use App\Models\ComparisonSet;
use App\Models\Group;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $ours = Group::firstOrCreate(['name' => 'Our Projects'], ['is_ours' => true]);
        $competitors = Group::firstOrCreate(['name' => 'Competitors Dubai'], ['is_ours' => false]);

        // NoWayOut and Horror Rooms run the same engine: /schedule-partial/
        // returns JSON with a week of server-rendered schedule HTML for all
        // rooms — one free HTTP request per site.
        $partialConfig = [
            'json_html_path' => 'data.schedule_partial',
            'card_selector' => '.day__block',
            'card_title_selector' => '.day__room-name',
            'slot_selector' => '.booking__slot-btn',
            'date_attr' => 'data-date',
            'time_attr' => 'data-time',
            'busy_matchers' => [['type' => 'class', 'value' => 'disabled']],
        ];

        foreach ([
            ['name' => 'No Way Out', 'website_url' => 'https://nowayout.ae', 'partial' => 'https://www.nowayout.ae/schedule-partial/?date={today}'],
            ['name' => 'Horror Rooms', 'website_url' => 'https://horrorrooms.ae', 'partial' => 'https://horrorrooms.ae/schedule-partial/?date={today}'],
        ] as $site) {
            $venue = Venue::firstOrCreate(
                ['name' => $site['name']],
                ['website_url' => $site['website_url'], 'group_id' => $ours->id],
            );

            $venue->scanSources()->firstOrCreate(
                ['name' => 'Schedule page'],
                [
                    'url' => $site['partial'],
                    'strategy' => 'generic',
                    'fetcher' => 'http',
                    'parse_mode' => 'detect_busy',
                    'parser_config' => $partialConfig,
                ],
            );
        }

        // Phobia exposes a per-room AJAX endpoint returning two weeks of
        // server-rendered slots. One source per room, matched by room id.
        $phobia = Venue::firstOrCreate(
            ['name' => 'Phobia'],
            ['website_url' => 'https://phobia.ae', 'group_id' => $ours->id],
        );

        foreach ([
            'Dark Room' => 1,
            'Vault' => 2,
            'Impossible Mission' => 3,
            'Sherlock' => 5,
            'Wizard School' => 6,
            'LIVE' => 7,
        ] as $roomName => $roomId) {
            $phobia->scanSources()->firstOrCreate(
                ['name' => $roomName],
                [
                    'url' => "https://phobia.ae/rooms/{$roomId}/schedule/?load_clicked=0&date={today}&players=4&actor=0",
                    'strategy' => 'generic',
                    'fetcher' => 'http',
                    'parse_mode' => 'detect_busy',
                    'parser_config' => [
                        'slot_selector' => '.booking__slot-btn',
                        'date_attr' => 'data-date',
                        'time_attr' => 'data-time',
                        'busy_matchers' => [['type' => 'class', 'value' => 'disabled']],
                        'http_headers' => [
                            'X-Requested-With' => 'XMLHttpRequest',
                            'Referer' => 'https://phobia.ae/',
                        ],
                    ],
                ],
            );
        }

        // Game Over Dubai: one Bookeo location page lists every game, so a
        // single scan (one Scrapfly request) covers all rooms per location.
        // Stable entry points; the 302 redirect issues a fresh session token.
        foreach ([
            [
                'venue' => 'Game Over Dubai (Palm Jumeirah)',
                'url' => 'https://bookeo.com/dubai-escape-booking',
            ],
            [
                'venue' => 'Game Over Dubai (Festival City)',
                'url' => 'https://bookeo.com/dubai2-escape-booking',
            ],
        ] as $location) {
            $venue = Venue::firstOrCreate(
                ['name' => $location['venue']],
                ['group_id' => $competitors->id, 'website_url' => 'https://dubai.escapegameover.ae'],
            );

            $venue->scanSources()->firstOrCreate(
                ['name' => 'Bookeo location page'],
                [
                    'url' => $location['url'],
                    'strategy' => 'bookeo',
                    'fetcher' => 'scrapfly',
                    'render_js' => true,
                    'anti_bot' => true,
                    'parse_mode' => 'detect_busy',
                    // Rooms are auto-discovered from game cards on first scan.
                ],
            );
        }

        // Escape The Room AE: their no-type Bookeo entry bounces to a custom
        // Angular app (slots via authenticated POST API), so we scan the
        // per-game Bookeo pages instead — stable ?type= entries, fresh
        // session token via redirect, real SOLD OUT flags.
        $etr = Venue::firstOrCreate(
            ['name' => 'Escape The Room Dubai'],
            ['group_id' => $competitors->id, 'website_url' => 'https://ae.escapetheroomgroup.com'],
        );

        // The old no-type location source (if present) is superseded.
        $etr->scanSources()
            ->where('name', 'Bookeo location page')
            ->update(['is_active' => false]);

        foreach ([
            'Nightmare' => '42556H3P9KY174D4BB2C7F',
            'The Prison' => '42556WTJAFP16D5ED09565',
            'Mafia Kingdom' => '425569TMRX716D5EE5AF9A',
            'Lost in Time' => '42556KU97TK175E009C0AE',
            'Z Virus' => '425569JPLLL17749284164',
        ] as $roomName => $type) {
            $etr->scanSources()->firstOrCreate(
                ['name' => $roomName],
                [
                    'url' => "https://bookeo.com/escapetheroomae?type={$type}",
                    'strategy' => 'bookeo',
                    'fetcher' => 'scrapfly',
                    'render_js' => true,
                    'anti_bot' => true,
                    'parse_mode' => 'detect_busy',
                ],
            );
        }

        // Escape Hunt Dubai: WP admin-ajax returns JSON for every game with
        // an explicit booked flag — one free request, all rooms.
        $escapeHunt = Venue::firstOrCreate(
            ['name' => 'Escape Hunt Dubai'],
            ['group_id' => $competitors->id, 'website_url' => 'https://escapehunt.com/ae/dubai/'],
        );

        $escapeHunt->scanSources()->firstOrCreate(
            ['name' => 'Availability API'],
            [
                'url' => 'https://escapehunt.com/ae/dubai/wp-admin/admin-ajax.php?action=get_first_available_date&displayType=DAY',
                'strategy' => 'json',
                'fetcher' => 'http',
                'parse_mode' => 'detect_busy',
                'parser_config' => [
                    'rooms_path' => 'game_list',
                    'room_name_key' => 'name',
                    'slots_key' => 'times',
                    'datetime_key' => 'bookTime',
                    'busy_key' => 'booked',
                    'http_headers' => ['Referer' => 'https://escapehunt.com/ae/dubai/booking/'],
                ],
                'notes' => 'Returns the first date with availability (= today while slots remain).',
            ],
        );

        // Questa: WooCommerce + Quest Game Booking plugin. The product page
        // embeds the weekly grid (data-open) and the booked map (QGBBookedSlots).
        $questa = Venue::firstOrCreate(
            ['name' => 'Questa'],
            ['group_id' => $competitors->id, 'website_url' => 'https://questa.ae'],
        );

        foreach ([
            'Gulliver' => 'gulliver',
            'Hospital 13' => 'hospital-13',
            'Jungle Adventure' => 'jungle-adventure',
            'Pixel Craft' => 'pixel-craft',
            'Silent Hill' => 'silent-hill',
            'Supernatural' => 'supernatural-under-the-djinns-spell',
            'The Black Phone' => 'the-black-phone',
            'Walled Secrets' => 'walled-secrets',
            'Wild West' => 'wild-west-golden-rush-1851-booking',
        ] as $roomName => $slug) {
            $questa->scanSources()->firstOrCreate(
                ['name' => $roomName],
                [
                    'url' => "https://questa.ae/product/{$slug}/",
                    'strategy' => 'questa',
                    'fetcher' => 'http',
                    'parse_mode' => 'detect_busy',
                ],
            );
        }

        // Not bookable yet ("coming soon" page, no schedule grid).
        $questa->scanSources()
            ->where('name', 'Supernatural')
            ->update(['is_active' => false, 'notes' => 'Room is coming soon, no schedule yet.']);

        // Brain Game books exclusively through Fever; the event page embeds
        // every session with available_tickets per room.
        $brainGame = Venue::firstOrCreate(
            ['name' => 'Brain Game Dubai'],
            ['group_id' => $competitors->id, 'website_url' => 'https://braingamedubai.com'],
        );

        $brainGame->scanSources()->firstOrCreate(
            ['name' => 'Fever event page'],
            [
                'url' => 'https://feverup.com/m/522768',
                'strategy' => 'fever',
                'fetcher' => 'http',
                'parse_mode' => 'detect_busy',
            ],
        );

        // Escape House (Livewire): room pages server-render today's AVAILABLE
        // slots only, so occupancy needs grid inference (future work) — for
        // now we track the available count per day.
        $escapeHouse = Venue::firstOrCreate(
            ['name' => 'Escape House'],
            ['group_id' => $competitors->id, 'website_url' => 'https://escapehouseuae.com'],
        );

        foreach ([
            'Cemetery' => 'cemetery',
            'Dubai' => 'dubai',
            'Exorcist' => 'exorcist',
            'Halloween' => 'halloween',
            "Jason's Home" => 'jasons-home',
            'Ouija' => 'ouija',
            'The School' => 'the-school',
            'Umm Al Duwais' => 'umm-all-duwais',
        ] as $roomName => $slug) {
            $escapeHouse->scanSources()->firstOrCreate(
                ['name' => $roomName],
                [
                    'url' => "https://escapehouseuae.com/rooms/{$slug}",
                    'strategy' => 'generic',
                    'fetcher' => 'http',
                    'parse_mode' => 'detect_busy',
                    'available_only' => true,
                    'parser_config' => [
                        'slot_selector' => '.available-time a.selectedDate',
                        'date_attr' => 'data-date',
                    ],
                    'notes' => 'Lists available slots only; a future slot that disappears between scans is counted as a booking.',
                ],
            );
        }

        // Blackout: /check-availability/{room_id}?date=... returns JSON with
        // availableHours (available-only, like Escape House). One free request
        // per room.
        $blackout = Venue::firstOrCreate(
            ['name' => 'Blackout'],
            ['group_id' => $competitors->id, 'website_url' => 'https://black-out.ae'],
        );

        foreach ([
            'Psychiatric' => 1,
            'Exorcism' => 2,
            'Torture' => 3,
            'Chaos' => 4,
        ] as $roomName => $roomId) {
            $blackout->scanSources()->firstOrCreate(
                ['name' => $roomName],
                [
                    'url' => "https://black-out.ae/check-availability/{$roomId}?date={today}",
                    'strategy' => 'json',
                    'fetcher' => 'http',
                    'parse_mode' => 'detect_busy',
                    'available_only' => true,
                    'parser_config' => [
                        'slots_path' => 'availableHours',
                        'time_key' => 'start',
                        'room_name_path' => 'availablePrices.0.room.title',
                        'http_headers' => ['X-Requested-With' => 'XMLHttpRequest'],
                    ],
                    'notes' => 'Endpoint returns available slots only; a future slot that disappears between scans is counted as a booking.',
                ],
            );
        }

        ComparisonSet::firstOrCreate(
            ['name' => 'Dubai: us vs competitors'],
            ['our_group_id' => $ours->id, 'competitor_group_id' => $competitors->id],
        );

        $this->call(TelegramTemplateSeeder::class);
    }
}
