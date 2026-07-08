<?php

namespace App\Filament\Widgets;

use App\Models\Room;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OccupancyToday extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Live occupancy — all rooms')
            ->query(
                Room::query()
                    ->with('venue.group')
                    ->join('venues', 'venues.id', '=', 'rooms.venue_id')
                    ->join('groups', 'groups.id', '=', 'venues.group_id')
                    ->where('rooms.is_active', true)
                    ->where('venues.is_active', true)
                    ->orderByDesc('groups.is_ours')
                    ->orderBy('venues.name')
                    ->select('rooms.*')
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('venue.group.name')
                    ->label('Group')
                    ->badge()
                    ->color(fn (Room $record) => $record->venue->group->is_ours ? 'success' : 'gray'),
                TextColumn::make('venue.name')
                    ->label('Venue'),
                TextColumn::make('name')
                    ->label('Room'),
                TextColumn::make('slots_total')
                    ->label('Slots')
                    ->state(fn (Room $record) => $record->under_maintenance ? '' : $record->todayStats()['total']),
                TextColumn::make('slots_sold_out')
                    ->label('Sold out')
                    ->state(fn (Room $record) => $record->under_maintenance ? '' : $record->todayStats()['sold_out']),
                TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->state(function (Room $record) {
                        if ($record->under_maintenance) {
                            return 'Maintenance';
                        }

                        $value = $record->todayStats()['occupancy'];

                        return $value === null ? '—' : $value.'%';
                    })
                    ->badge()
                    ->color(function (Room $record) {
                        if ($record->under_maintenance) {
                            return 'warning';
                        }

                        $value = $record->todayStats()['occupancy'];

                        return match (true) {
                            $value === null => 'gray',
                            $value >= 70 => 'success',
                            $value >= 40 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->icon(fn (Room $record) => $record->under_maintenance ? 'heroicon-o-wrench-screwdriver' : null),
                TextColumn::make('released')
                    ->label('Released')
                    ->tooltip('Slot was sold out and became available again — possible fake booking')
                    ->state(fn (Room $record) => $record->under_maintenance ? '' : ($record->todayStats()['released'] ?: '')),
            ]);
    }
}
