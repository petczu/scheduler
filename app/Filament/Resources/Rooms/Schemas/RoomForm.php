<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('venue_id')
                    ->relationship('venue', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('scan_source_id')
                    ->relationship('scanSource', 'name')
                    ->helperText('The source page this room appears on. Rooms are also auto-created when a scan finds a new game card.'),
                TextInput::make('match_label')
                    ->helperText('Card title on the source page, if it differs from the room name.'),
                Toggle::make('is_active')
                    ->default(true),
                Toggle::make('under_maintenance')
                    ->label('Under maintenance')
                    ->helperText('Room is closed by the operator. Excluded from occupancy metrics so its unavailable slots are not counted as demand.'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
