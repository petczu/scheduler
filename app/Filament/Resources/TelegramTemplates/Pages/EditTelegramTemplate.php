<?php

namespace App\Filament\Resources\TelegramTemplates\Pages;

use App\Filament\Resources\TelegramTemplates\TelegramTemplateResource;
use App\Models\TelegramTemplate;
use Filament\Resources\Pages\EditRecord;

class EditTelegramTemplate extends EditRecord
{
    protected static string $resource = TelegramTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TelegramTemplateResource::sendTestAction(),
        ];
    }

    protected function afterSave(): void
    {
        // Drop the in-request template cache so previews reflect the edit.
        TelegramTemplate::forgetCache();
    }
}
