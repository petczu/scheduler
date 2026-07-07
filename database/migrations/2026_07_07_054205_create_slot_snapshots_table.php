<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_run_id')->constrained()->cascadeOnDelete();
            // Slot start in the venue's local timezone
            $table->dateTime('slot_at');
            $table->string('status'); // available|sold_out|unknown
            $table->string('raw_label')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['room_id', 'slot_at', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_snapshots');
    }
};
