<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whitelist of phone numbers allowed to authorize with the bot.
        Schema::create('telegram_allowed_phones', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->string('phone');          // as entered
            $table->string('phone_normalized')->unique(); // digits only
            $table->timestamps();
        });

        // People who started the bot and (maybe) verified their phone.
        Schema::create('telegram_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('phone')->nullable();
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
