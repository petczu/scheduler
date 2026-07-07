<?php

namespace App\Filament\Resources\ScanSources\Pages;

use App\Filament\Resources\ScanSources\ScanSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScanSource extends EditRecord
{
    protected static string $resource = ScanSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
