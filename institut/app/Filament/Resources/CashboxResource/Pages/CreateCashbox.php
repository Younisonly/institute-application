<?php

namespace App\Filament\Resources\CashboxResource\Pages;

use App\Filament\Resources\CashboxResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCashbox extends CreateRecord
{
    protected static string $resource = CashboxResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        if (empty($data['code'])) {
            $data['code'] = \App\Models\Cashbox::generateNextCode();
        }

        return $data;
    }
}
