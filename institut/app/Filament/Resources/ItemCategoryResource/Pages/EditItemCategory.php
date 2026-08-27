<?php

namespace App\Filament\Resources\ItemCategoryResource\Pages;

use App\Filament\Resources\ItemCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditItemCategory extends EditRecord
{
    protected static string $resource = ItemCategoryResource::class;

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
