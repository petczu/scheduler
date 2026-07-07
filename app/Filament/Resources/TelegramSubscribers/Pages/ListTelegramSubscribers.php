<?php

namespace App\Filament\Resources\TelegramSubscribers\Pages;

use App\Filament\Resources\TelegramSubscribers\TelegramSubscriberResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramSubscribers extends ListRecords
{
    protected static string $resource = TelegramSubscriberResource::class;
}
