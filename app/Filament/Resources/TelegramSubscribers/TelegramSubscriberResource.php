<?php

namespace App\Filament\Resources\TelegramSubscribers;

use App\Filament\Resources\TelegramSubscribers\Pages\ListTelegramSubscribers;
use App\Models\TelegramSubscriber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TelegramSubscriberResource extends Resource
{
    protected static ?string $model = TelegramSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Subscribers';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('first_name')->label('Name')->searchable(),
                TextColumn::make('username')
                    ->formatStateUsing(fn (?string $state) => $state ? '@'.$state : '—')
                    ->searchable(),
                // Encrypted at rest — shown decrypted, but not SQL-searchable.
                TextColumn::make('phone'),
                IconColumn::make('authorized')->boolean(),
                TextColumn::make('authorized_at')->dateTime()->sortable()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('authorized'),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (TelegramSubscriber $record) => $record->authorized ? 'Revoke' : 'Authorize')
                    ->icon(fn (TelegramSubscriber $record) => $record->authorized ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (TelegramSubscriber $record) => $record->authorized ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (TelegramSubscriber $record) => $record->update([
                        'authorized' => ! $record->authorized,
                        'authorized_at' => $record->authorized ? null : now(),
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramSubscribers::route('/'),
        ];
    }
}
