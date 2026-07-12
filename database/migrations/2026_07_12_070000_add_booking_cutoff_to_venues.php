<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking sites close online booking some minutes before a slot starts:
     * the slot shows as disabled/vanishes even though nobody bought it. Any
     * reading inside that window is not evidence of a booking, so occupancy
     * math must ignore it. The cutoff is venue-specific and admin-editable.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedSmallInteger('booking_cutoff_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('booking_cutoff_minutes');
        });
    }
};
