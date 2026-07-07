<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compact daily rollup of occupancy per room, so year-scale analysis does not
 * have to scan the raw slot_snapshots table (~one row per room per day).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_room_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('slots_total')->default(0);
            $table->unsignedSmallInteger('sold_out')->default(0);
            $table->unsignedTinyInteger('occupancy')->nullable(); // percent
            $table->unsignedSmallInteger('released')->default(0);  // fake bookings
            // Morning (slot hour < 17) vs evening (>= 17) split.
            $table->unsignedSmallInteger('am_total')->default(0);
            $table->unsignedSmallInteger('am_sold_out')->default(0);
            $table->unsignedSmallInteger('pm_total')->default(0);
            $table->unsignedSmallInteger('pm_sold_out')->default(0);
            $table->timestamps();

            $table->unique(['room_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_room_stats');
    }
};
