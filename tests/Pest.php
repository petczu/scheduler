<?php

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit');

// The template cache is static (per request/command in production); reset it
// between tests so DB refreshes are reflected.
pest()->beforeEach(function () {
    App\Models\TelegramTemplate::forgetCache();
})->in('Feature', 'Unit');
