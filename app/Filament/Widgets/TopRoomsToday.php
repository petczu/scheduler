<?php

namespace App\Filament\Widgets;

use App\Models\DailyRoomStat;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Today's most-booked rooms across everyone, ranked. Reads the hourly
 * daily_room_stats rollup so it can be sorted by occupancy in SQL.
 */
class TopRoomsToday extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = Carbon::now('Asia/Dubai')->toDateString();

        return $table
            ->heading('Top rooms by occupancy today')
            ->query(
                DailyRoomStat::query()
                    ->join('rooms as r', 'r.id', '=', 'daily_room_stats.room_id')
                    ->where('daily_room_stats.date', $today)
                    ->where('r.is_active', true)
                    ->where('r.under_maintenance', false)
                    ->with('room.venue.group')
                    ->select('daily_room_stats.*')
                    ->orderByDesc('daily_room_stats.occupancy')
                    ->orderByDesc('daily_room_stats.sold_out')
            )
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('room.venue.group.name')
                    ->label('Group')
                    ->badge()
                    ->color(fn (DailyRoomStat $record) => $record->room?->venue?->group?->is_ours ? 'success' : 'gray'),
                TextColumn::make('room.venue.name')
                    ->label('Venue'),
                TextColumn::make('room.name')
                    ->label('Room'),
                TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : "{$state}%")
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('sold_out')
                    ->label('Booked')
                    ->formatStateUsing(fn ($state, DailyRoomStat $record) => "{$state}/{$record->slots_total}"),
                TextColumn::make('released')
                    ->label('Released')
                    ->tooltip('Slots freed up after being sold out — possible fake bookings')
                    ->formatStateUsing(fn ($state) => $state ?: ''),
            ]);
    }
}
