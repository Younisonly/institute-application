<?php

namespace App\Filament\Resources\ProgramTypeResource\Pages;

use App\Filament\Resources\ProgramTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgramType extends EditRecord
{
    protected static string $resource = ProgramTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make()->label(__('general.restore')),
            Actions\ForceDeleteAction::make()
                ->label(__('general.force_delete'))
                ->requiresConfirmation(),
        ];
    }
}
