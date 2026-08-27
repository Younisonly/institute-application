<?php

namespace App\Filament\Resources\PeriodResource\Pages;

use App\Filament\Resources\PeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPeriod extends EditRecord
{
    protected static string $resource = PeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription(__('general.period_delete_hint')),
            Actions\RestoreAction::make()->label(__('general.restore')),
            Actions\ForceDeleteAction::make()
                ->label(__('general.force_delete'))
                ->requiresConfirmation(),
        ];
    }
}
