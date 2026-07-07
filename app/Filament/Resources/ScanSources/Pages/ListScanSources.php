<?php

namespace App\Filament\Resources\ScanSources\Pages;

use App\Filament\Resources\ScanSources\ScanSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScanSources extends ListRecords
{
    protected static string $resource = ScanSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
