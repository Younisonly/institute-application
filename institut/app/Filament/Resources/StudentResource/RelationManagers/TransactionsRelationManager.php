<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\StudentTransaction;
use App\Services\ReceiptNumberService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->color(fn (?StudentTransaction $record): string => match ($record?->type) {
                        'payment' => 'success',
                        'refund' => 'warning',
                        default => 'danger',
                    })
                    ->weight('semibold'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('receipt_no')->label(__('general.receipt_no'))->placeholder('—'),
                TextColumn::make('registration.course.name')
                    ->label(__('general.course'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->native(false)
                    ->label(__('general.type'))
                    ->options([
                        'charge' => __('general.charge'),
                        'payment' => __('general.payment'),
                        'refund' => __('general.refund'),
                    ]),
                Tables\Filters\SelectFilter::make('registration_id')->native(false)
                    ->label(__('general.registration'))
                    ->options(fn (): array => $this->getOwnerRecord()->registrations()
                        ->with('course')
                        ->get()
                        ->mapWithKeys(fn ($registration): array => [
                            $registration->id => $registration->course->name.' — '.$registration->start_month,
                        ])
                        ->all()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('general.add_charge'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('danger')
                    ->form($this->transactionFields(false))
                    ->action(function (array $data): void {
                        $this->createTransaction('charge', $data);
                    }),
                Tables\Actions\Action::make('recordPayment')
                    ->label(__('general.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form($this->transactionFields(true))
                    ->action(function (array $data): void {
                        $this->createTransaction('payment', $data, withReceipt: true);
                    }),
                Tables\Actions\Action::make('recordRefund')
                    ->label(__('general.record_refund'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form($this->transactionFields(true))
                    ->action(function (array $data): void {
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

    private function transactionFields(bool $withMethod): array
    {
        return [
            MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            Select::make('registration_id')->native(false)
                ->label(__('general.registration'))
                ->options(fn (): array => $this->getOwnerRecord()->registrations()
                    ->with('course')
                    ->get()
                    ->mapWithKeys(fn ($registration): array => [
                        $registration->id => $registration->course->name.' — '.$registration->start_month.' ('.$registration->status.')',
                    ])
                    ->all())
                ->searchable()
                ->nullable(),
            ...($withMethod ? [
                ...PaymentDetails::fields(),
            ] : []),
            TextInput::make('description')->label(__('general.description'))->maxLength(255),
        ];
    }

    private function createTransaction(string $type, array $data, bool $withReceipt = false): void
    {
        DB::transaction(function () use ($type, $data, $withReceipt): void {
            $payload = [
                'student_id' => $this->getOwnerRecord()->id,
                'registration_id' => $data['registration_id'] ?? null,
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

            if ($withReceipt) {
                $payload['receipt_no'] = app(ReceiptNumberService::class)->next();
            }

            StudentTransaction::create($payload);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }
}
