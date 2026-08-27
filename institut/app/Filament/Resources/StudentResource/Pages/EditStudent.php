<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    public function _finishUpload($name, $tmpPath, $isMultiple, $append = false): void
    {
        if (str($name)->startsWith('data.photo_path') && ! $isMultiple) {
            $this->data['photo_path'] = [];
        }

        parent::_finishUpload($name, $tmpPath, $isMultiple, $append);
    }

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
