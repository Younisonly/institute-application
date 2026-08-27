<?php

namespace App\Filament\Resources\CourseBatchResource\Pages;

use App\Filament\Resources\CourseBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCourseBatches extends ListRecords
{
    protected static string $resource = CourseBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}