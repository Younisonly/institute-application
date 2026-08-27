<?php

namespace App\Filament\Resources\ProgramTypeResource\Pages;

use App\Filament\Resources\ProgramTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProgramType extends CreateRecord
{
    protected static string $resource = ProgramTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label(__('general.cancel'))
                ->url(ProgramTypeResource::getUrl('index')),
        ];
    }
}
