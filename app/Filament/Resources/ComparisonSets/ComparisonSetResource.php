<?php

namespace App\Filament\Resources\ComparisonSets;

use App\Filament\Resources\ComparisonSets\Pages\CreateComparisonSet;
use App\Filament\Resources\ComparisonSets\Pages\EditComparisonSet;
use App\Filament\Resources\ComparisonSets\Pages\ListComparisonSets;
use App\Filament\Resources\ComparisonSets\Schemas\ComparisonSetForm;
use App\Filament\Resources\ComparisonSets\Tables\ComparisonSetsTable;
use App\Models\ComparisonSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComparisonSetResource extends Resource
{
    protected static ?string $model = ComparisonSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ComparisonSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComparisonSetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComparisonSets::route('/'),
            'create' => CreateComparisonSet::route('/create'),
            'edit' => EditComparisonSet::route('/{record}/edit'),
        ];
    }
}
