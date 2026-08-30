<?php

namespace App\Filament\Resources\CashboxResource\Pages;

use App\Filament\Resources\CashboxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashboxes extends ListRecords
{
    protected static string $resource = CashboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
