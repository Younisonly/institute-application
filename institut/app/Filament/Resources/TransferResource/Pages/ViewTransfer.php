<?php

namespace App\Filament\Resources\TransferResource\Pages;

use App\Filament\Resources\TransferResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTransfer extends ViewRecord
{
    protected static string $resource = TransferResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextEntry::make('fromAccount.name')->label(__('general.from_account')),
                TextEntry::make('toAccount.name')->label(__('general.to_account')),
                TextEntry::make('amount')->label(__('general.amount'))->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency')),
                TextEntry::make('reference')->label(__('general.reference'))->placeholder('—'),
                TextEntry::make('description')->label(__('general.description'))->placeholder('—'),
                TextEntry::make('voided_at')->label(__('general.status'))->formatStateUsing(fn ($state) => $state ? __('general.voided') : __('general.active')),
            ]);
    }
}
