<?php

namespace App\Filament\Resources\TeachingSessionResource\Pages;

use App\Filament\Resources\TeachingSessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTeachingSession extends CreateRecord
{
    protected static string $resource = TeachingSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
