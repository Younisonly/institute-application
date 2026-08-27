<?php

namespace App\Filament\Resources\PartyTypeResource\Pages;

use App\Filament\Resources\PartyTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartyType extends CreateRecord
{
    protected static string $resource = PartyTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
