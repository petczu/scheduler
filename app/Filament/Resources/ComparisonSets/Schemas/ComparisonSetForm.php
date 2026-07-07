<?php

namespace App\Filament\Resources\ComparisonSets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ComparisonSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('our_group_id')
                    ->relationship('ourGroup', 'name')
                    ->required(),
                Select::make('competitor_group_id')
                    ->relationship('competitorGroup', 'name')
                    ->required(),
            ]);
    }
}
