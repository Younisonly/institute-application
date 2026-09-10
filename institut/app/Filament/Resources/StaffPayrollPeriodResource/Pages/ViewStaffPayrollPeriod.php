<?php

namespace App\Filament\Resources\StaffPayrollPeriodResource\Pages;

use App\Filament\Resources\StaffPayrollPeriodResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStaffPayrollPeriod extends ViewRecord
{
    protected static string $resource = StaffPayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
