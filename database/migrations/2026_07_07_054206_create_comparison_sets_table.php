<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('our_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('competitor_group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_sets');
    }
};
