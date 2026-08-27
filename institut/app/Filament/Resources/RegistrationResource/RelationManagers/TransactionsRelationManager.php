<?php

namespace App\Filament\Resources\RegistrationResource\RelationManagers;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\Registration;
use App\Models\StudentTransaction;
use App\Services\ReceiptNumberService;
use Filament\Forms\Components\DatePicker;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.transactions');
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false;
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
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'charge', 'transfer_debit' => 'danger',
                        'payment', 'transfer_credit' => 'success',
                        'refund', 'write_off' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state).' '.__('general.currency'))
                    ->color(fn (?StudentTransaction $record): string => match ($record?->type) {
                        'payment' => 'success',
                        'refund' => 'warning',
                        default => 'danger',
                    })
                    ->weight('semibold'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('receipt_no')->label(__('general.receipt_no'))->placeholder('—'),
                TextColumn::make('method')
                    ->label(__('general.payment_method'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? __("general.method_{$state}") : '')
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('general.add_charge'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('danger')
                    ->form([
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->createTransaction('charge', $data);
                    }),
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('general.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        ...PaymentDetails::fields(),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $reg = Registration::query()->withTotals()->find($this->getOwnerRecord()->id);
                        $balance = max(0, (float) ($reg?->balance ?? 0));
                        if ((float) $data['amount'] > $balance) {
                            throw ValidationException::withMessages([
                                'amount' => __('general.payment_exceeds_balance'),
                            ]);
                        }

                        $this->createTransaction('payment', $data, withReceipt: true);
                    }),
                Tables\Actions\Action::make('recordRefund')
                    ->label(__('general.record_refund'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form([
                        Select::make('original_transaction_id')
                            ->label(__('general.original_payment'))
                            ->required()
                            ->options(function (): array {
                                $registration = $this->getOwnerRecord();
                                $payments = StudentTransaction::query()
                                    ->where('registration_id', $registration->id)
                                    ->where('type', 'payment')
                                    ->whereNull('voided_at')
                                    ->get();

                                return $payments->mapWithKeys(function (StudentTransaction $tx): array {
                                    $alreadyRefunded = (float) $tx->refunds()->sum('amount');
                                    $available = max(0, (float) $tx->amount - $alreadyRefunded);
                                    $receipt = $tx->receipt_no ? "#{$tx->receipt_no}" : "ID:{$tx->id}";
                                    $date = $tx->date ? $tx->date->format('d/m/Y') : '';

                                    return [
                                        $tx->id => "{$receipt} - ".number_format((float) $tx->amount).' '.__('general.currency').' ('.__('general.available').': '.number_format($available)." {$date})",
                                    ];
                                })->toArray();
                            }),
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        ...PaymentDetails::fields(),
                        TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $originalTx = StudentTransaction::query()->whereNull('voided_at')->find($data['original_transaction_id']);
                        if (! $originalTx || $originalTx->type !== 'payment') {
                            throw ValidationException::withMessages([
                                'original_transaction_id' => __('general.no_eligible_payment_for_refund'),
                            ]);
                        }

                        $alreadyRefunded = (float) $originalTx->refunds()->sum('amount');
                        $maxRefundable = max(0, (float) $originalTx->amount - $alreadyRefunded);

                        if ((float) $data['amount'] > $maxRefundable) {
                            throw ValidationException::withMessages([
                                'amount' => __('general.refund_exceeds_payment'),
                            ]);
                        }

                        $this->createTransaction('refund', $data);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('printReceipt')
                    ->label(__('general.print_receipt'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (StudentTransaction $record): string => route('receipts.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (?StudentTransaction $record): bool => $record !== null && $record->type === 'payment' && $record->receipt_no !== null && $record->voided_at === null),
                Tables\Actions\Action::make('void')
                    ->label(__('general.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->modalHeading(__('general.void'))
                    ->modalDescription(__('general.must_provide_void_reason'))
                    ->form([
                        TextInput::make('void_reason')
                            ->label(__('general.void_reason'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (StudentTransaction $record, array $data): void {
                        $record->void($data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?StudentTransaction $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    private function createTransaction(string $type, array $data, bool $withReceipt = false): void
    {
        DB::transaction(function () use ($type, $data, $withReceipt): void {
            $payload = [
                'student_id' => $this->getOwnerRecord()->student_id,
                'registration_id' => $this->getOwnerRecord()->id,
                'original_transaction_id' => $type === 'refund' ? ($data['original_transaction_id'] ?? null) : null,
                'type' => $type,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ];

            if ($withReceipt || $type === 'refund') {
                $payload['receipt_no'] = app(ReceiptNumberService::class)->next();
            }

            StudentTransaction::create($payload);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }
}
