<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One scan source = one page fetch (e.g. a Bookeo location page
        // listing every game). Rooms are matched to cards on the page.
        Schema::create('scan_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            // Which parser understands the page: bookeo | generic
            $table->string('strategy')->default('generic');
            // How the page is fetched: http (free) | scrapfly
            $table->string('fetcher')->default('http');
            $table->boolean('render_js')->default(false);
            $table->boolean('anti_bot')->default(false);
            // detect_busy: everything is free unless matched as busy
            // detect_free: everything is busy unless matched as free
            $table->string('parse_mode')->default('detect_busy');
            // available_only: the source lists only free slots (booked ones
            // vanish). A future slot that disappears between scans is booked.
            $table->boolean('available_only')->default(false);
            // Generic parser config: slot selector, matchers, card selectors
            $table->json('parser_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Card title on the source page this room corresponds to.
            // Defaults to the room name when empty.
            $table->string('match_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('scan_sources');
    }
};
