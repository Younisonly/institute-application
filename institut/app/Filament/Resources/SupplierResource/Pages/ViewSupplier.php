<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.supplier'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('general.name'))
                            ->weight('bold'),
                        TextEntry::make('phone')
                            ->label(__('general.phone'))
                            ->icon('heroicon-m-phone')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label(__('general.address'))
                            ->placeholder('—'),
                    ]),
                Section::make(__('general.summary'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('debt')
                            ->label(__('general.total_charge'))
                            ->getStateUsing(fn ($record): string => number_format($record->debt).' '.__('general.currency'))
                            ->color('warning')
                            ->weight('bold'),
                        TextEntry::make('paid')
                            ->label(__('general.paid'))
                            ->getStateUsing(fn ($record): string => number_format($record->paid).' '.__('general.currency'))
                            ->color('success')
                            ->weight('bold'),
                        TextEntry::make('balance')
                            ->label(__('general.balance'))
                            ->getStateUsing(fn ($record): string => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) $record->balance))
                            ->color(fn ($record): string => $record->balance > 0 ? 'danger' : 'success')
                            ->weight('bold'),
                    ]),
            ]);
    }
}
