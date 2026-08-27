<?php

namespace App\Filament\Resources\OtherPersonResource\RelationManagers;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\OtherPeopleTransaction;
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
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? __('general.income') : __('general.expense'))
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('receipt_no')->label(__('general.receipt_no'))->placeholder('—'),
                TextColumn::make('method')->label(__('general.payment_method'))->badge()->formatStateUsing(fn (string $state): string => __("general.method_{$state}")),
                TextColumn::make('incomeCategory.name')->label(__('general.income_category'))->placeholder('—'),
                TextColumn::make('expenseCategory.name')->label(__('general.expense_category'))->placeholder('—'),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')->label(__('general.status'))->badge()->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recordIncome')
                    ->label(__('general.record_income'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form($this->transactionFields('in'))
                    ->action(function (array $data): void {
                        $this->createTransaction('in', $data);
                    }),
                Tables\Actions\Action::make('recordExpense')
                    ->label(__('general.record_expense'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                    ->form($this->transactionFields('out'))
                    ->action(function (array $data): void {
                        $this->createTransaction('out', $data);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('printVoucher')
                    ->label(__('general.print_receipt'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (OtherPeopleTransaction $record): string => route('vouchers.other.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (?OtherPeopleTransaction $record): bool => $record !== null && $record->receipt_no !== null && $record->voided_at === null),
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
                    ->action(function (OtherPeopleTransaction $record, array $data): void {
                        $record->void($data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?OtherPeopleTransaction $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    private function transactionFields(string $type): array
    {
        return [
            MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            ...PaymentDetails::fields(),
            Select::make('income_category_id')->native(false)
                ->label(__('general.income_category'))
                ->options(fn (): array => IncomeCategory::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->visible(fn (): bool => $type === 'in'),
            Select::make('expense_category_id')->native(false)
                ->label(__('general.expense_category'))
                ->options(fn (): array => ExpenseCategory::query()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->visible(fn (): bool => $type === 'out'),
            TextInput::make('description')->label(__('general.description'))->maxLength(255),
        ];
    }

    private function createTransaction(string $type, array $data): void
    {
        DB::transaction(function () use ($type, $data): void {
            OtherPeopleTransaction::create([
                'other_person_id' => $this->getOwnerRecord()->id,
                'type' => $type,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'income_category_id' => $data['income_category_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'description' => $data['description'] ?? null,
                'receipt_no' => app(ReceiptNumberService::class)->next(),
                'created_by' => Auth::id(),
            ]);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }
}
