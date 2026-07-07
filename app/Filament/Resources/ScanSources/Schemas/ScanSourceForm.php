<?php

namespace App\Filament\Resources\ScanSources\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScanSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Source')
                    ->columns(2)
                    ->components([
                        Select::make('venue_id')
                            ->relationship('venue', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->helperText('E.g. "Bookeo location page" or "Website booking widget".'),
                        TextInput::make('url')
                            ->label('URL')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Page with the schedule. Supports the {today} placeholder (venue-local date, Y-m-d). For Bookeo use the stable entry like bookeo.com/dubai-escape-booking — redirects are followed automatically. A location page listing several games feeds all rooms in one scan.'),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),

                Section::make('Scraping')
                    ->columns(2)
                    ->components([
                        Select::make('fetcher')
                            ->options([
                                'http' => 'HTTP (free, no JS)',
                                'scrapfly' => 'Scrapfly (anti-bot + JS rendering)',
                            ])
                            ->default('http')
                            ->required(),
                        Select::make('strategy')
                            ->label('Parser')
                            ->options([
                                'generic' => 'Generic (selectors from config)',
                                'bookeo' => 'Bookeo',
                                'json' => 'JSON API (paths from config)',
                                'questa' => 'Questa / QGB WordPress plugin',
                                'fever' => 'Fever (feverup.com event page)',
                            ])
                            ->default('generic')
                            ->required(),
                        Toggle::make('render_js')
                            ->label('Render JS')
                            ->helperText('+5 Scrapfly credits. Required for JavaScript calendars (mandatory for Bookeo).'),
                        Toggle::make('anti_bot')
                            ->label('Anti-bot bypass')
                            ->helperText('ASP + residential proxies. ~30 credits per request (required for Bookeo).'),
                    ]),

                Section::make('Parsing rules')
                    ->components([
                        Select::make('parse_mode')
                            ->options([
                                'detect_busy' => 'detect_busy — everything is free, match busy slots',
                                'detect_free' => 'detect_free — everything is busy, match free slots',
                            ])
                            ->default('detect_busy')
                            ->required(),
                        Toggle::make('available_only')
                            ->label('Available-only source')
                            ->helperText('The page/endpoint lists only free slots (booked ones vanish). A future slot that disappears between scans is counted as a booking; a slot gone because its time passed is ignored.'),
                        CodeEditor::make('parser_config')
                            ->language(Language::Json)
                            ->formatStateUsing(fn ($state) => filled($state)
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? json_decode($state, true) : null)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (filled($value) && json_decode($value, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                                    $fail('Invalid JSON.');
                                }
                            })
                            ->helperText('slot_selector, card_selector + card_title_selector (multi-room pages), busy_matchers/free_matchers (text|class|css|attr|style), datetime_attr, date_attr. Can be left empty for Bookeo — sensible defaults apply.'),
                        Textarea::make('notes'),
                    ]),
            ]);
    }
}
