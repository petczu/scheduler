<?php

namespace App\Console\Commands;

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

        $this->info("Alerts sent: {$sent}.");

        return self::SUCCESS;
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
