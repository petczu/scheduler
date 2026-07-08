<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalyticsTrend;
use App\Filament\Widgets\MonthlyBreakdown;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Analytics extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $slug = 'analytics';

    // Dashboard subclasses take their path from $routePath (default '/'); set
    // a distinct one so this second dashboard registers its own route.
    protected static string $routePath = '/analytics';

    protected static ?int $navigationSort = -1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    Select::make('period')
                        ->label('Period')
                        ->options([
                            '7' => 'Last 7 days',
                            '30' => 'Last 30 days',
                            '90' => 'Last 90 days',
                            '365' => 'Last 12 months',
                        ])
                        ->default('30')
                        ->selectablePlaceholder(false),
                    Select::make('level')
                        ->label('Compare by')
                        ->options([
                            'venue' => 'Venue',
                            'room' => 'Room',
                        ])
                        ->default('venue')
                        ->selectablePlaceholder(false),
                    Select::make('metric')
                        ->label('Metric')
                        ->options([
                            'occupancy' => 'Occupancy %',
                            'booked' => 'Booked slots',
                        ])
                        ->default('occupancy')
                        ->selectablePlaceholder(false),
                ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            AnalyticsTrend::class,
            MonthlyBreakdown::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
