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
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
