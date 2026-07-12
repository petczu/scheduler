<?php

namespace App\Filament\Resources\Venues\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('website_url')
                    ->url(),
                TextInput::make('timezone')
                    ->required()
                    ->default('Asia/Dubai'),
                TextInput::make('booking_cutoff_minutes')
                    ->label('Booking cutoff (minutes)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(720)
                    ->default(30)
                    ->helperText('How long before a slot starts the site closes online booking. Readings inside this window are ignored — a slot disabled there is not counted as booked.'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
