<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\SupplierTransaction;
use App\Services\ReceiptNumberService;
use Filament\Forms\Components\DatePicker;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.payments');
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    protected function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    protected function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'payment' ? __('general.payment') : __('general.charge'))
                    ->color(fn (string $state): string => $state === 'payment' ? 'danger' : 'gray'),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('receipt_no')->label(__('general.receipt_no'))->placeholder('—'),
                TextColumn::make('method')
                    ->label(__('general.payment_method'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => __("general.method_{$state}")),
                TextColumn::make('transaction_ref')->label(__('general.transaction_ref'))->placeholder('—')->toggleable(),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('general.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form([
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1)->suffix(__('general.currency')),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y')->required(),
                        ...PaymentDetails::fields(),
                        TextInput::make('reference')->label(__('general.reference'))->maxLength(100),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        DB::transaction(function () use ($data): void {
                            SupplierTransaction::create([
                                'supplier_id' => $this->getOwnerRecord()->id,
                                'type' => 'payment',
                                'amount' => $data['amount'],
                                'date' => $data['date'],
                                'method' => $data['method'] ?? 'cash',
                                'bank_id' => $data['bank_id'] ?? null,
                                'wallet_id' => $data['wallet_id'] ?? null,
                                'transaction_ref' => $data['transaction_ref'] ?? null,
                                'reference' => $data['reference'] ?? null,
                                'description' => $data['description'] ?? null,
                                'receipt_no' => app(ReceiptNumberService::class)->next(),
                                'created_by' => Auth::id(),
                            ]);
                        });

                        Notification::make()->title(__('general.saved'))->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('printVoucher')
                    ->label(__('general.print_receipt'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (SupplierTransaction $record): string => route('vouchers.supplier.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (?SupplierTransaction $record): bool => $record !== null && $record->receipt_no !== null && $record->voided_at === null),
                Tables\Actions\Action::make('void')
                    ->label(__('general.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.void'))
                    ->modalDescription(__('general.must_provide_void_reason'))
                    ->form([
                        TextInput::make('void_reason')->label(__('general.void_reason'))->required()->maxLength(255),
                    ])
                    ->action(function (SupplierTransaction $record, array $data): void {
                        $record->void($data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?SupplierTransaction $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }
}
