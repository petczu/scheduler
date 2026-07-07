<?php

namespace Database\Seeders;

use App\Models\TelegramTemplate;
use Illuminate\Database\Seeder;

class TelegramTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Insert any missing templates; never overwrite admin edits.
        foreach (TelegramTemplate::defaults() as $key => $default) {
            TelegramTemplate::firstOrCreate(
                ['key' => $key],
                ['description' => $default['description'], 'body' => $default['body']],
            );
        }
    }
}
