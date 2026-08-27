<?php

namespace App\Filament\Resources\CourseBatchResource\Pages;

use App\Filament\Resources\CourseBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseBatch extends CreateRecord
{
    protected static string $resource = CourseBatchResource::class;

    protected ?string $periodId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->periodId = isset($data['periods']) && $data['periods'] !== '' ? (string) $data['periods'] : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->periodId !== null) {
            $this->record->periods()->sync([(int) $this->periodId]);
        }
    }
}