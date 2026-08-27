<?php

namespace App\Filament\Resources\OtherPersonResource\Pages;

use App\Filament\Resources\OtherPersonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOtherPerson extends CreateRecord
{
    protected static string $resource = OtherPersonResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
