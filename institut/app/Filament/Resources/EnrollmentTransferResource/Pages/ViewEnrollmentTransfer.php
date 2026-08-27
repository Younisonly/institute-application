<?php

namespace App\Filament\Resources\EnrollmentTransferResource\Pages;

use App\Filament\Resources\EnrollmentTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrollmentTransfer extends ViewRecord
{
    protected static string $resource = EnrollmentTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printTransfer')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('enrollment-transfers.print', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
