<?php

namespace App\Filament\Resources\OtherPersonResource\Pages;

use App\Filament\Resources\OtherPersonResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOtherPerson extends ViewRecord
{
    protected static string $resource = OtherPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
