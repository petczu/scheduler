<?php

namespace App\Filament\Resources\ScanRuns;

use App\Filament\Resources\ScanRuns\Pages\ListScanRuns;
use App\Filament\Resources\ScanRuns\Pages\ViewScanRun;
use App\Filament\Resources\ScanRuns\Schemas\ScanRunForm;
use App\Filament\Resources\ScanRuns\Schemas\ScanRunInfolist;
use App\Filament\Resources\ScanRuns\Tables\ScanRunsTable;
use App\Models\ScanRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScanRunResource extends Resource
{
    protected static ?string $model = ScanRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ScanRunForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ScanRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScanRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScanRuns::route('/'),
            'view' => ViewScanRun::route('/{record}'),
        ];
    }
}
