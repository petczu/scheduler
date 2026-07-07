<?php

namespace App\Filament\Resources\ScanRuns\Pages;

use App\Filament\Resources\ScanRuns\ScanRunResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewScanRun extends ViewRecord
{
    protected static string $resource = ScanRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
