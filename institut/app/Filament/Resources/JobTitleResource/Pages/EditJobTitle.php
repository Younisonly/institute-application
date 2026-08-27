<?php

namespace App\Filament\Resources\JobTitleResource\Pages;

use App\Filament\Resources\JobTitleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobTitle extends EditRecord
{
    protected static string $resource = JobTitleResource::class;

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
