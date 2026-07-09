<?php

namespace App\Console\Commands;

use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\TelegramTemplate;
use App\Telegram\Notifier;
use App\Telegram\OccupancyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendTelegramAlerts extends Command
{
    protected $signature = 'telegram:alerts';

    protected $description = 'Detect and send event alerts (fake bookings, sell-outs, scan failures) to Telegram';

    public function handle(OccupancyReport $report, Notifier $notifier): int
    {
        $sent = 0;

        foreach ($report->detectAlerts() as $alert) {
            if ($notifier->send($alert['kind'], $alert['text'], $alert['signature'])) {
                $sent++;
            }
        }

        $sent += $this->reportScanFailures($notifier);
        $sent += $this->reportStalledScanning($notifier);

        $this->info("Alerts sent: {$sent}.");

        return self::SUCCESS;
    }

    /**
     * Watchdog: with queued scans, a stopped Horizon (or a stuck scheduler)
     * would silently halt scanning. Alert if no scan succeeded recently.
     * Free HTTP sources run every 5 minutes, so 30 minutes of silence is wrong.
     */
    protected function reportStalledScanning(Notifier $notifier): int
    {
        $threshold = (int) config('services.telegram.stall_minutes', 30);

        $latest = ScanRun::where('status', 'success')->max('finished_at');

        if ($latest === null) {
            return 0; // nothing scanned yet (fresh install) — not a stall
        }

        $minutes = (int) abs(CarbonImmutable::parse($latest)->diffInMinutes(now()));

        if ($minutes < $threshold) {
            return 0;
        }

        // One alert per stall window (bucketed) so it doesn't repeat hourly forever.
        $bucket = CarbonImmutable::now()->floorHour()->format('Y-m-d-H');
        $text = "🚨 <b>Scanning stalled</b>\nNo successful scan for {$minutes} min. Check Horizon / the scheduler.";

        return $notifier->send('alert_stalled', $text, "alert_stalled:{$bucket}") ? 1 : 0;
    }

    /**
     * Alert when a source's two most recent runs both failed — the data for
     * that competitor is going stale.
     */
    protected function reportScanFailures(Notifier $notifier): int
    {
        $today = CarbonImmutable::now('Asia/Dubai')->format('Y-m-d');
        $sent = 0;

        $sources = ScanSource::query()
            ->where('is_active', true)
            ->with(['venue', 'scanRuns' => fn ($q) => $q->latest('id')->limit(2)])
            ->get();

        foreach ($sources as $source) {
            $runs = $source->scanRuns;

            if ($runs->count() < 2 || $runs->contains(fn ($r) => $r->status !== 'failed')) {
                continue;
            }

            $error = mb_substr((string) $runs->first()->error, 0, 200);
            $text = TelegramTemplate::render('alert_scan_failed', [
                'venue' => $source->venue->name,
                'source' => $source->name,
                'error' => $error,
            ]);

            if ($notifier->send('alert_scan_failed', $text, "alert_scan_failed:source={$source->id}:{$today}")) {
                $sent++;
            }
        }

        return $sent;
    }
}
