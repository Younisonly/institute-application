<?php

namespace App\Filament\Resources\PartyTypeResource\Pages;

use App\Filament\Resources\PartyTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartyTypes extends ListRecords
{
    protected static string $resource = PartyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
