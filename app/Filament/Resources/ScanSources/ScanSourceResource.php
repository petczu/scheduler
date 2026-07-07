<?php

namespace App\Filament\Resources\ScanSources;

use App\Filament\Resources\ScanSources\Pages\CreateScanSource;
use App\Filament\Resources\ScanSources\Pages\EditScanSource;
use App\Filament\Resources\ScanSources\Pages\ListScanSources;
use App\Filament\Resources\ScanSources\Schemas\ScanSourceForm;
use App\Filament\Resources\ScanSources\Tables\ScanSourcesTable;
use App\Models\ScanSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScanSourceResource extends Resource
{
    protected static ?string $model = ScanSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ScanSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScanSourcesTable::configure($table);
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
            'index' => ListScanSources::route('/'),
            'create' => CreateScanSource::route('/create'),
            'edit' => EditScanSource::route('/{record}/edit'),
        ];
    }
}
