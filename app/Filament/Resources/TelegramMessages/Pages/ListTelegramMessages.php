<?php

namespace App\Filament\Resources\TelegramMessages\Pages;

use App\Filament\Resources\TelegramMessages\TelegramMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramMessages extends ListRecords
{
    protected static string $resource = TelegramMessageResource::class;
}
