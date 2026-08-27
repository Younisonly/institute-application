<?php

namespace App\Filament\Resources\IncomeCategoryResource\Pages;

use App\Filament\Resources\IncomeCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncomeCategory extends CreateRecord
{
    protected static string $resource = IncomeCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
