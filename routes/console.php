<?php

use Illuminate\Support\Facades\Schedule;

// Scanning runs synchronously (no queue worker required) and in the
// background so it never blocks the per-minute scheduler. Business hours
// are Dubai time.
//
// Free HTTP sources (our sites + most competitors) are scanned around the
// clock every 5 minutes — free, and lets us observe whether bookings also
// happen overnight. This is what catches fake bookings (a future slot that
// briefly disappears).
Schedule::command('scan:run --sync --fetcher=http')
    ->everyFiveMinutes()
    ->timezone('Asia/Dubai')
    ->runInBackground()
    ->withoutOverlapping();

// Paid Scrapfly sources (Game Over, Escape The Room): every 15 minutes,
// around the clock. Higher credit spend, accepted while testing.
Schedule::command('scan:run --sync --fetcher=scrapfly')
    ->everyFifteenMinutes()
    ->timezone('Asia/Dubai')
    ->runInBackground()
    ->withoutOverlapping();

// Event alerts run a few minutes after each scan, on fresh data.
Schedule::command('telegram:alerts')
    ->hourly()
    ->timezone('Asia/Dubai')
    ->between('09:10', '23:59');

// Daily digests: morning and evening snapshots.
Schedule::command('telegram:digest morning')
    ->dailyAt('09:05')
    ->timezone('Asia/Dubai');

Schedule::command('telegram:digest evening')
    ->dailyAt('21:00')
    ->timezone('Asia/Dubai');

// Roll raw snapshots into compact daily stats (kept forever for year-scale
// analysis). Runs hourly so today's point stays fresh through the day (and
// yesterday is finalized right after midnight); it also re-rolls yesterday,
// which is idempotent and cheap.
Schedule::command('stats:rollup --today')
    ->hourly()
    ->timezone('Asia/Dubai')
    ->runInBackground()
    ->withoutOverlapping();

// Process incoming bot updates (onboarding, phone verification).
// The command no-ops when a webhook secret is set, so this is safe to leave
// scheduled in both polling and webhook modes.
Schedule::command('telegram:poll')
    ->everyMinute()
    ->withoutOverlapping();
