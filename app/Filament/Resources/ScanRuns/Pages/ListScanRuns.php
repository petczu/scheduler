<?php

namespace App\Filament\Resources\ScanRuns\Pages;

use App\Filament\Resources\ScanRuns\ScanRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScanRuns extends ListRecords
{
    protected static string $resource = ScanRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
