<?php

namespace App\Filament\Resources\PartyTypeResource\Pages;

use App\Filament\Resources\PartyTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartyType extends EditRecord
{
    protected static string $resource = PartyTypeResource::class;

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
