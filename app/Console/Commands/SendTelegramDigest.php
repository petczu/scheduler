<?php

namespace App\Console\Commands;

use App\Telegram\Notifier;
use App\Telegram\OccupancyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendTelegramDigest extends Command
{
    protected $signature = 'telegram:digest {period=morning : morning|evening}';

    protected $description = 'Send the occupancy digest (ours vs competitors) to Telegram';

    public function handle(OccupancyReport $report, Notifier $notifier): int
    {
        $period = $this->argument('period') === 'evening' ? 'evening' : 'morning';
        $text = $report->digest($period);

        // One digest per period per day.
        $date = CarbonImmutable::now('Asia/Dubai')->format('Y-m-d');
        $sent = $notifier->send("digest_{$period}", $text, "digest_{$period}:{$date}");

        $this->info($sent ? "Digest ({$period}) sent." : "Digest ({$period}) not sent (duplicate, disabled, or failed).");
        $this->line($text);

        return self::SUCCESS;
    }
}
