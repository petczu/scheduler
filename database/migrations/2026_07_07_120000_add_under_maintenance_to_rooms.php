<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Room closed by the operator (renovation, repairs). Its slots are
            // unavailable for reasons unrelated to demand, so it is excluded
            // from occupancy metrics.
            $table->boolean('under_maintenance')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('under_maintenance');
        });
    }
};
