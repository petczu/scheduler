<?php

namespace App\Filament\Resources\ScanRuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ScanRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('scanSource.venue.name')
                    ->label('Venue'),
                TextEntry::make('scanSource.name')
                    ->label('Source'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('fetcher')
                    ->placeholder('-'),
                TextEntry::make('credits_cost')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('rooms_found')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('slots_found')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('error')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('raw_html_path')
                    ->placeholder('-'),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
