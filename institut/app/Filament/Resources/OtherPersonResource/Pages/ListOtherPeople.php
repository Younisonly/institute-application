<?php

namespace App\Filament\Resources\OtherPersonResource\Pages;

use App\Filament\Resources\OtherPersonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherPeople extends ListRecords
{
    protected static string $resource = OtherPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
