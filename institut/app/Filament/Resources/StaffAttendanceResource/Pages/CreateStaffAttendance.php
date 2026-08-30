<?php

namespace App\Filament\Resources\StaffAttendanceResource\Pages;

use App\Filament\Resources\StaffAttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffAttendance extends CreateRecord
{
    protected static string $resource = StaffAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
