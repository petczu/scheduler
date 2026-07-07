<?php

namespace App\Filament\Resources\TelegramMessages;

use App\Filament\Resources\TelegramMessages\Pages\ListTelegramMessages;
use App\Models\TelegramMessage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TelegramMessageResource extends Resource
{
    protected static ?string $model = TelegramMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $recordTitleAttribute = 'kind';

    protected static ?string $navigationLabel = 'Telegram log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('text')
                    ->formatStateUsing(fn (string $state) => strip_tags($state))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('error')->limit(60)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'sent' => 'Sent',
                    'failed' => 'Failed',
                    'skipped' => 'Skipped',
                ]),
                SelectFilter::make('kind')->options([
                    'digest_morning' => 'Digest (morning)',
                    'digest_evening' => 'Digest (evening)',
                    'alert_fake_booking' => 'Alert: fake booking',
                    'alert_sold_out' => 'Alert: sold out',
                    'alert_scan_failed' => 'Alert: scan failed',
                    'test' => 'Test',
                ]),
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
            'index' => ListTelegramMessages::route('/'),
        ];
    }
}
