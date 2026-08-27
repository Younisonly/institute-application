<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('general.expense'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('date')
                            ->label(__('general.date'))
                            ->date('d/m/Y'),
                        TextEntry::make('category.name')
                            ->label(__('general.expense_category'))
                            ->badge()
                            ->color('danger'),
                        TextEntry::make('amount')
                            ->label(__('general.amount'))
                            ->formatStateUsing(fn (string $state): string => number_format((float) $state).' '.__('general.currency'))
                            ->weight('bold')
                            ->color('danger'),
                        TextEntry::make('payment_method')
                            ->label(__('general.payment_method'))
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn (string $state): string => __("general.method_{$state}")),
                        TextEntry::make('bank.name')
                            ->label(__('general.bank'))
                            ->visible(fn ($record): bool => $record->payment_method === 'bank'),
                        TextEntry::make('wallet.name')
                            ->label(__('general.wallet'))
                            ->visible(fn ($record): bool => $record->payment_method === 'wallet'),
                        TextEntry::make('transaction_ref')
                            ->label(__('general.transaction_ref'))
                            ->visible(fn ($record): bool => filled($record->transaction_ref)),
                        TextEntry::make('description')
                            ->label(__('general.description'))
                            ->columnSpanFull(),
                        TextEntry::make('attachment_path')
                            ->label(__('general.attachment'))
                            ->formatStateUsing(fn ($state): string => $state ? Storage::url($state) : '—')
                            ->url(fn ($record): ?string => $record->attachment_path ? Storage::url($record->attachment_path) : null)
                            ->openUrlInNewTab()
                            ->visible(fn ($record): bool => filled($record->attachment_path)),
                    ]),
                Section::make(__('general.voided'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('voided_at')
                            ->label(__('general.date'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('void_reason')
                            ->label(__('general.void_reason')),
                    ])
                    ->visible(fn ($record): bool => $record->voided_at !== null),
            ]);
    }
}
