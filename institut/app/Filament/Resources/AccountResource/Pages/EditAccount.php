<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    /**
     * code/type are identity fields: restoring them on save keeps the chart
     * stable for posted lines (the type drives the normal balance sign).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->is_system || $this->record->lines()->exists()) {
            $data['code'] = $this->record->code;
            $data['type'] = $this->record->type;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}