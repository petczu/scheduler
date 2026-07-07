<?php

namespace App\Filament\Resources\TelegramAllowedPhones;

use App\Filament\Resources\TelegramAllowedPhones\Pages\ManageTelegramAllowedPhones;
use App\Models\TelegramAllowedPhone;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramAllowedPhoneResource extends Resource
{
    protected static ?string $model = TelegramAllowedPhone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Allowed phones';

    protected static ?string $recordTitleAttribute = 'phone';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->helperText('Who this number belongs to (e.g. "Peter", "Ops manager").'),
            TextInput::make('phone')
                ->label('Phone number')
                ->required()
                ->tel()
                ->helperText('Full international format, e.g. +971 50 123 4567. Matched by digits only.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                // Encrypted at rest — shown decrypted, but not SQL-searchable.
                TextColumn::make('phone'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTelegramAllowedPhones::route('/'),
        ];
    }
}
