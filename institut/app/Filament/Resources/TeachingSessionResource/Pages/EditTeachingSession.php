<?php

namespace App\Filament\Resources\TeachingSessionResource\Pages;

use App\Filament\Resources\TeachingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeachingSession extends EditRecord
{
    protected static string $resource = TeachingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
