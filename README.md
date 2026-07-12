# Schedule Checker

Slot-occupancy monitoring for escape rooms: our brands vs competitors. Laravel 13 + Filament 5, scraping via Scrapfly (protected sites) or plain HTTP (regular sites).

## Quick start

The project runs under [Laravel Herd](https://herd.laravel.com): **http://schedule-checker.test/admin**

Login: `peter@czuiko.com` / `password` (change it).

```bash
# dependencies and the SQLite database are already set up; when moving the project:
composer install
php artisan migrate --seed
php artisan make:filament-user
```

Add your Scrapfly key to `.env` (1,000 free credits: https://scrapfly.io):

```
SCRAPFLY_KEY=...
```

## How it works

- **Groups** — "Our Projects" (nowayout.ae, horrorrooms.ae, phobia.ae) and "Competitors Dubai". The system is generic: any groups, any comparisons (Comparison Sets).
- **Venues** — a site/location within a group.
- **Scan Sources** — a page to scrape (URL + fetch/parse settings). One source can be a location page listing every game, so a single fetch feeds all rooms — one Scrapfly request (~30 credits) per location, regardless of room count.
- **Rooms** — escape rooms, **auto-discovered** from game cards on the source page during the first scan (matched by `match_label`).
- **Scan Runs** — scan history (status, credit cost, errors, raw HTML in `storage/app/private/scans/`).
- **Slot Snapshots** — a snapshot of every slot on every scan. The transition history is what catches fake bookings (sold out → available again).

### Scan Source configuration

| Field | Values |
|---|---|
| `fetcher` | `http` — free, no JS; `scrapfly` — anti-bot + JS rendering |
| `strategy` | `generic` — selectors from config; `bookeo` — Bookeo markup; `json` — JSON API (paths from config); `questa` — QGB WordPress plugin; `fever` — feverup.com event page |
| `parse_mode` | `detect_busy` — everything is free, match busy; `detect_free` — the opposite |
| `parser_config` | JSON: `slot_selector`, `card_selector`/`card_title_selector`, `busy_matchers`/`free_matchers`, `datetime_attr`, `date_attr`, `time_attr`, `json_html_path`, `http_headers` |

URLs support the `{today}` placeholder (replaced with the venue-local date).

Per-strategy `parser_config` keys:

- **json** — `rooms_path`, `room_name_key`, `slots_key`, `datetime_key`, `busy_key`, `busy_value` (e.g. Escape Hunt's `admin-ajax.php?action=get_first_available_date`).
- **questa** — no config needed; reads the weekly grid from `data-open` and the booked map from `window.QGBBookedSlots`; optional `days_ahead` (default 7).
- **fever** — no config needed; extracts `"default_label"` room groups and `starts_at_iso`/`available_tickets` sessions from the event page.

Example `parser_config` for a regular site with several rooms on one page:

```json
{
  "card_selector": ".room-card",
  "card_title_selector": ".room-title",
  "slot_selector": ".time-slot",
  "date_attr": "data-date",
  "busy_matchers": [
    {"type": "text", "value": "SOLD OUT"},
    {"type": "class", "value": "disabled"},
    {"type": "css", "value": ".badge-sold"},
    {"type": "attr", "name": "aria-disabled", "value": "true"},
    {"type": "style", "value": "line-through"}
  ]
}
```

Omit `card_selector` for single-room pages — the room is created automatically from the source.

### Running scans

```bash
php artisan scan:run              # all active sources
php artisan scan:run --source=1   # a single source
php artisan scan:run --group=2    # one group only
php artisan scan:run --sync       # run immediately and print results
```

There is also a **Scan now** button on the Scan Sources list in the admin panel.

The schedule (hourly, 09:00–24:00 Dubai time) is already configured in `routes/console.php`. Locally run `php artisan schedule:work`; on a server set up cron for `php artisan schedule:run`.

The queue is currently `sync` (for local use). On a server switch to `QUEUE_CONNECTION=database` and run `php artisan queue:work`.

### Available-only sources

Some sites (Escape House, Blackout) list only *free* slots — a booked slot simply disappears. Mark such a source **Available-only**. On each scan the system compares against the previous one: a slot that was free before and is now gone, **while its start time is still in the future**, is recorded as a booking (synthetic sold-out). A slot that vanished because its time already passed is ignored — it is not a booking. Occupancy for these sources therefore builds up from the second scan onward.

### Dashboard metrics

- **Occupancy** — % of today's slots sold out, based on the latest scan.
- **Released** — a slot was sold out and became available again: a likely fake booking by a competitor.

### Booking cutoff

Sites close online booking some minutes before a slot starts — the slot then shows as disabled (or disappears) even though nobody bought it. Each venue has a **Booking cutoff (minutes)** setting (default 30): readings taken inside that window are ignored, and a slot is judged by the last scan taken *before* start − cutoff. Set it to match each site's real cutoff — too small overcounts (passed slots look booked), too large misses genuine last-minute bookings.

## Telegram bot

Digests and alerts are delivered to **authorized subscribers** — people who verified their phone number with the bot.

**Setup**

1. Create a bot via [@BotFather](https://t.me/BotFather), then set in `.env`:
   ```
   TELEGRAM_ENABLED=true
   TELEGRAM_BOT_TOKEN=<token>
   ```
2. Add allowed phone numbers in the admin: **Allowed phones** (full international format, e.g. `+971 50 123 4567`).
3. Keep incoming updates flowing: locally run `php artisan telegram:poll` (scheduled every minute), or in production register the webhook (`POST /telegram/webhook/{TELEGRAM_WEBHOOK_SECRET}`).

**How a user subscribes**

They open the bot and send `/start`, then tap **Share phone number**. If the number is on the allow-list they're authorized and start receiving messages; otherwise they're rejected. `/stop` unsubscribes. Manage/authorize/revoke people under **Subscribers**; every sent message is logged under **Telegram log**.

**Messages**

- 🌅 Morning digest (09:05) and 🌆 Evening digest (21:00) — occupancy, ours vs competitors, leader of the day.
- ⚠️ Possible fake booking (competitor freed a sold-out slot), 🔥 Competitor selling out (≥ `TELEGRAM_SOLD_OUT_THRESHOLD`%), 🛠 Source not scanning (two failed runs).

All message texts (digests, alerts, and the bot's own replies) are **editable in the admin** under **Message templates** — each template has `{placeholders}` and supports Telegram HTML (`<b>`, `<code>`). Edits take effect immediately; unedited templates fall back to the built-in English defaults.

Commands: `telegram:test`, `telegram:digest morning|evening`, `telegram:alerts`, `telegram:poll`.

### Tests

```bash
php artisan test
```

## Bookeo specifics (Game Over)

- Entry URLs are stable: `https://bookeo.com/dubai-escape-booking` (Palm Jumeirah) and `https://bookeo.com/dubai2-escape-booking` (Festival City) — the 302 redirect issues a fresh session token by itself. Each location page lists every game.
- Bookeo blocklists datacenter IPs and shows a CAPTCHA to non-browsers — these sources need `fetcher=scrapfly`, `render_js=true`, `anti_bot=true` (residential proxies are forced automatically, ~30 credits per request).
- `BookeoParser` targets the real markup: `.cbtce_box` cards, `.cbtce_boxTitle` names, `button.cbtce_boxSlot` slots, `cbtce_boxSlot_eventLabel_full`/`disabled`/"SOLD OUT" for busy, slot dates from the `cbtce_submit('...-YYYY-MM-DD')` payload. `parser_config` overrides the defaults if Bookeo changes markup.
- If a scan hits a block/CAPTCHA page, the run is marked **failed** with a clear error instead of pretending success with 0 slots.
