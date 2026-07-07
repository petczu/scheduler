<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            // digest_morning | digest_evening | alert_fake_booking |
            // alert_sold_out | alert_scan_failed | test
            $table->string('kind');
            // Dedup key (e.g. "alert_sold_out:room=12:2026-07-07"); unique so
            // the same alert is never sent twice.
            $table->string('signature')->nullable()->unique();
            $table->text('text');
            $table->string('status')->default('pending'); // sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
