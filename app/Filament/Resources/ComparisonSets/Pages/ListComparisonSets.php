<?php

namespace App\Filament\Resources\ComparisonSets\Pages;

use App\Filament\Resources\ComparisonSets\ComparisonSetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComparisonSets extends ListRecords
{
    protected static string $resource = ComparisonSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
