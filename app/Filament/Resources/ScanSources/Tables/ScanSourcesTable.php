<?php

namespace App\Filament\Resources\ScanSources\Tables;

use App\Jobs\ScanSourceJob;
use App\Models\ScanSource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScanSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('venue.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('strategy'),
                TextColumn::make('fetcher'),
                TextColumn::make('rooms_count')
                    ->counts('rooms')
                    ->label('Rooms'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('scan')
                    ->label('Scan now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (ScanSource $record) {
                        ScanSourceJob::dispatch($record);

                        Notification::make()
                            ->title("Scan for \"{$record->name}\" has been queued")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
