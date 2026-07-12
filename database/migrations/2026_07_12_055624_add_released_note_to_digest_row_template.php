<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Append {released_note} to the digest_row template so digests show
     * bookings that were released after being sold out. Only rows still
     * matching the old default are touched — manual edits are preserved.
     */
    public function up(): void
    {
        DB::table('telegram_templates')
            ->where('key', 'digest_row')
            ->where('body', '{venue} — <b>{occupancy}</b> ({sold}/{total})')
            ->update([
                'body' => '{venue} — <b>{occupancy}</b> ({sold}/{total}){released_note}',
            ]);
    }

    public function down(): void
    {
        DB::table('telegram_templates')
            ->where('key', 'digest_row')
            ->where('body', '{venue} — <b>{occupancy}</b> ({sold}/{total}){released_note}')
            ->update(['body' => '{venue} — <b>{occupancy}</b> ({sold}/{total})']);
    }
};
