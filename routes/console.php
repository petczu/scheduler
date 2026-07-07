<?php

use Illuminate\Support\Facades\Schedule;

// Scan all active rooms every hour during business hours (Dubai time).
// Frequent scans are what let us detect fake bookings (sold_out -> available).
Schedule::command('scan:run')
    ->hourly()
    ->timezone('Asia/Dubai')
    ->between('09:00', '23:59');

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

// Process incoming bot updates (onboarding, phone verification).
// For production, use the webhook route instead of polling.
Schedule::command('telegram:poll')
    ->everyMinute()
    ->withoutOverlapping();
