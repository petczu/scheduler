<?php

namespace App\Filament\Resources\ScanRuns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScanRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('scanSource.venue.name')
                    ->label('Venue')
                    ->searchable(),
                TextColumn::make('scanSource.name')
                    ->label('Source')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('fetcher'),
                TextColumn::make('credits_cost')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rooms_found')
                    ->numeric(),
                TextColumn::make('slots_found')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('error')
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'running' => 'Running',
                        'pending' => 'Pending',
                    ]),
                SelectFilter::make('scan_source_id')
                    ->label('Source')
                    ->relationship('scanSource', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
