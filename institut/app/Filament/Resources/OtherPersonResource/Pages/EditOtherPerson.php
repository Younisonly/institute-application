<?php

namespace App\Filament\Resources\OtherPersonResource\Pages;

use App\Filament\Resources\OtherPersonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOtherPerson extends EditRecord
{
    protected static string $resource = OtherPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
