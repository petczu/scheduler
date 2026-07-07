<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_source_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|running|success|failed
            $table->string('fetcher')->nullable();        // http|scrapfly (actually used)
            $table->unsignedInteger('credits_cost')->nullable();
            $table->unsignedInteger('slots_found')->nullable();
            $table->unsignedInteger('rooms_found')->nullable();
            $table->text('error')->nullable();
            $table->string('raw_html_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['scan_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_runs');
    }
};
