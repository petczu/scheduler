<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store phone numbers encrypted at rest. Matching the allow-list is done via
 * a deterministic HMAC hash (phone_hash) so we never keep a plaintext number
 * we can query. These tables are empty at this point, so they are recreated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('telegram_subscribers');
        Schema::dropIfExists('telegram_allowed_phones');

        Schema::create('telegram_allowed_phones', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->text('phone');                 // encrypted at the app layer
            $table->string('phone_hash')->unique(); // HMAC of the digits, for matching
            $table->timestamps();
        });

        Schema::create('telegram_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->text('phone')->nullable();      // encrypted at the app layer
            $table->string('first_name')->nullable();
            $table->string('username')->nullable();
            $table->boolean('authorized')->default(false);
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_subscribers');
        Schema::dropIfExists('telegram_allowed_phones');
    }
};
