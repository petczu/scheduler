<?php

namespace App\Filament\Resources\TelegramAllowedPhones\Pages;

use App\Filament\Resources\TelegramAllowedPhones\TelegramAllowedPhoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTelegramAllowedPhones extends ManageRecords
{
    protected static string $resource = TelegramAllowedPhoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
