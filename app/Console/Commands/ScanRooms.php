<?php

namespace App\Console\Commands;

use App\Jobs\ScanSourceJob;
use App\Models\ScanSource;
use Illuminate\Console\Command;

class ScanRooms extends Command
{
    protected $signature = 'scan:run
        {--source= : Scan a single source by id}
        {--group= : Scan only sources whose venue belongs to this group id}
        {--sync : Run scans immediately instead of queueing}';

    protected $description = 'Dispatch availability scans for all active scan sources';

    public function handle(): int
    {
        $sources = ScanSource::query()
            ->where('is_active', true)
            ->whereHas('venue', fn ($q) => $q->where('is_active', true))
            ->when($this->option('source'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('group'), fn ($q, $groupId) => $q->whereHas(
                'venue', fn ($v) => $v->where('group_id', $groupId)
            ))
            ->get();

        foreach ($sources as $source) {
            if ($this->option('sync')) {
                ScanSourceJob::dispatchSync($source);
                $latest = $source->scanRuns()->latest()->first();
                $this->line(sprintf(
                    '%s / %s: %s (%s rooms, %s slots)%s',
                    $source->venue->name,
                    $source->name,
                    $latest?->status,
                    $latest?->rooms_found ?? 0,
                    $latest?->slots_found ?? 0,
                    $latest?->error ? ' — '.$latest->error : '',
                ));
            } else {
                ScanSourceJob::dispatch($source);
            }
        }

        $this->info("Dispatched {$sources->count()} scan(s).");

        return self::SUCCESS;
    }
}
