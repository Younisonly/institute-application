<?php

namespace App\Filament\Resources\TeachingSessionResource\Pages;

use App\Filament\Resources\TeachingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeachingSessions extends ListRecords
{
    protected static string $resource = TeachingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
