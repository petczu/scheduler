<?php

namespace App\Filament\Resources\TelegramTemplates;

use App\Filament\Resources\TelegramTemplates\Pages\EditTelegramTemplate;
use App\Filament\Resources\TelegramTemplates\Pages\ListTelegramTemplates;
use App\Models\TelegramSubscriber;
use App\Models\TelegramTemplate;
use App\Telegram\Notifier;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramTemplateResource extends Resource
{
    protected static ?string $model = TelegramTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Message templates';

    protected static ?string $recordTitleAttribute = 'key';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('description')
                ->disabled()
                ->dehydrated(false),
            Textarea::make('body')
                ->required()
                ->rows(6)
                ->columnSpanFull()
                ->helperText(fn (?TelegramTemplate $record) => static::placeholderHint($record)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('description')->searchable()->wrap(),
                TextColumn::make('body')
                    ->formatStateUsing(fn (string $state) => str_replace("\n", ' ⏎ ', strip_tags($state)))
                    ->limit(70)
                    ->wrap(),
            ])
            ->recordActions([
                static::sendTestAction(),
                EditAction::make(),
            ]);
    }

    /**
     * Renders the template with sample placeholder values and sends it to a
     * chosen recipient (or all authorized subscribers) as a live preview.
     */
    public static function sendTestAction(): Action
    {
        return Action::make('sendTest')
            ->label('Send test')
            ->icon('heroicon-o-paper-airplane')
            ->form([
                Select::make('recipient')
                    ->label('Send preview to')
                    ->options(static::recipientOptions())
                    ->default('all')
                    ->required(),
            ])
            ->action(function (TelegramTemplate $record, array $data) {
                $text = TelegramTemplate::render($record->key, static::sampleVars());

                $chatIds = $data['recipient'] === 'all'
                    ? TelegramSubscriber::authorized()->pluck('chat_id')->all()
                    : [$data['recipient']];

                $ok = app(Notifier::class)->sendToChats($chatIds, 'test', $text);

                Notification::make()
                    ->title($ok ? 'Preview sent' : 'Not sent — check Telegram config / recipients')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    protected static function recipientOptions(): array
    {
        $options = ['all' => 'All authorized subscribers'];

        foreach (TelegramSubscriber::authorized()->get() as $sub) {
            $label = $sub->first_name ?: ($sub->username ? '@'.$sub->username : $sub->chat_id);
            $options[$sub->chat_id] = $label.' ('.$sub->chat_id.')';
        }

        return $options;
    }

    /**
     * Sample values so previews render with realistic placeholders.
     *
     * @return array<string, string>
     */
    protected static function sampleVars(): array
    {
        return [
            'date' => CarbonImmutable::now('Asia/Dubai')->format('D, d M Y'),
            'venue' => 'Game Over (Palm Jumeirah)',
            'occupancy' => '75',
            'sold' => '6',
            'total' => '8',
            'where' => 'Game Over / Orient Express',
            'count' => '2',
            'source' => 'Bookeo location page',
            'error' => 'HTTP 500',
        ];
    }

    protected static function placeholderHint(?TelegramTemplate $record): string
    {
        $placeholders = $record ? (TelegramTemplate::defaults()[$record->key]['placeholders'] ?? '') : '';

        $base = 'HTML is supported: <b>bold</b>, <code>mono</code>. Use \n for a new line.';

        return $placeholders === ''
            ? $base
            : "Placeholders: {$placeholders}. {$base}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramTemplates::route('/'),
            'edit' => EditTelegramTemplate::route('/{record}/edit'),
        ];
    }
}
