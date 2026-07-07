<?php

namespace App\Filament\Resources\ComparisonSets\Pages;

use App\Filament\Resources\ComparisonSets\ComparisonSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComparisonSet extends EditRecord
{
    protected static string $resource = ComparisonSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
