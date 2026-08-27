<?php

namespace App\Filament\Resources\StaffResource\RelationManagers;

use App\Filament\Forms\Components\PaymentDetails;
use App\Models\StaffTransaction;
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
use Illuminate\Support\HtmlString;

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
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'salary' => 'success',
                        'advance' => 'warning',
                        'repayment' => 'info',
                        'deduction' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state) . ' ' . __('general.currency'))
                    ->weight('semibold'),
                TextColumn::make('salary_month')
                    ->label(__('general.salary_month'))
                    ->placeholder('—'),
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('reference')->label(__('general.reference'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')->label(__('general.description'))->limit(40),
                TextColumn::make('voided_at')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('general.voided') : '')
                    ->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('salaryPayment')
                    ->label(__('general.salary_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form($this->salaryAndDeductionFields())
                    ->action(function (array $data): void {
                        $this->createTransaction('salary', $data);
                    }),
                Tables\Actions\Action::make('giveAdvance')
                    ->label(__('general.give_advance'))
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->form($this->transactionFields())
                    ->action(function (array $data): void {
                        $this->createTransaction('advance', $data);
                    }),
                Tables\Actions\Action::make('repayment')
                    ->label(__('general.repayment'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->form($this->transactionFields())
                    ->action(function (array $data): void {
                        $this->createTransaction('repayment', $data);
                    }),
                Tables\Actions\Action::make('deduction')
                    ->label(__('general.deduction'))
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->form($this->salaryAndDeductionFields())
                    ->action(function (array $data): void {
                        $this->createTransaction('deduction', $data);
                    }),
            ])
            ->actions([
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
                    ->action(function (StaffTransaction $record, array $data): void {
                        $record->void($data['void_reason']);
                        Notification::make()->title(__('general.voided'))->success()->send();
                    })
                    ->visible(fn (?StaffTransaction $record): bool => $record !== null && $record->voided_at === null),
            ]);
    }

    private function getSalaryMonthOptions(): array
    {
        $options = [];
        $start = now()->subMonths(6);
        for ($i = 0; $i <= 12; $i++) {
            $date = $start->copy()->addMonths($i);
            $options[$date->format('Y-m')] = $date->format('F Y');
        }
        return $options;
    }

    private function getPayableSalary(string $salaryMonth): float
    {
        $staff = $this->getOwnerRecord();
        if ($staff->salary_type !== 'monthly') {
            return 99999999.99; // No strict cap for non-monthly (or handle separately)
        }

        $base = (float) $staff->salary_value;
        
        $totalPaidThisMonth = (float) $staff->transactions()
            ->whereIn('type', ['salary', 'deduction'])
            ->whereNull('voided_at')
            ->where('salary_month', $salaryMonth)
            ->sum('amount');

        return max(0, $base - $totalPaidThisMonth);
    }

    private function salaryAndDeductionFields(): array
    {
        return [
            \Filament\Forms\Components\Select::make('salary_month')
                ->label(__('general.salary_month'))
                ->options($this->getSalaryMonthOptions())
                ->default(now()->format('Y-m'))
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('max_payable', $this->getPayableSalary($state ?? now()->format('Y-m')));
                }),
            \Filament\Forms\Components\Placeholder::make('month_warning')
                ->hiddenLabel()
                ->content(fn ($get) => new HtmlString('<span style="color: red; font-weight: bold;">' . __('general.month_not_ended_warning') . '</span>'))
                ->visible(fn ($get) => $get('salary_month') === now()->format('Y-m') && now()->day < 28),
            \Filament\Forms\Components\Placeholder::make('base_salary')
                ->label(__('general.base_salary'))
                ->content(fn () => number_format((float) $this->getOwnerRecord()->salary_value) . ' ' . __('general.currency')),
            \Filament\Forms\Components\Placeholder::make('outstanding_advances')
                ->label(__('general.outstanding_advances'))
                ->content(fn () => number_format((float) $this->getOwnerRecord()->outstanding_advance) . ' ' . __('general.currency')),
            \Filament\Forms\Components\Placeholder::make('max_payable_placeholder')
                ->label(__('general.max_payable'))
                ->content(fn ($get) => number_format((float) $this->getPayableSalary($get('salary_month') ?? now()->format('Y-m'))) . ' ' . __('general.currency')),
            MoneyInput::make('amount')
                ->label(__('general.amount'))
                ->required()
                ->minValue(1)
                ->rules([
                    fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $max = $this->getPayableSalary($get('salary_month') ?? now()->format('Y-m'));
                        if ((float) $value > $max) {
                            $fail(__('general.max_salary_exceeded', ['max' => number_format($max)]));
                        }
                    },
                ]),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            ...PaymentDetails::fields(),
            TextInput::make('reference')->label(__('general.reference'))->maxLength(50),
            TextInput::make('description')->label(__('general.description'))->maxLength(255),
        ];
    }

    private function transactionFields(): array
    {
        return [
            MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
            DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
            ...PaymentDetails::fields(),
            TextInput::make('reference')->label(__('general.reference'))->maxLength(50),
            TextInput::make('description')->label(__('general.description'))->maxLength(255),
        ];
    }

    private function createTransaction(string $type, array $data): void
    {
        DB::transaction(function () use ($type, $data): void {
            StaffTransaction::create([
                'staff_id' => $this->getOwnerRecord()->id,
                'type' => $type,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'salary_month' => $data['salary_month'] ?? null,
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        Notification::make()->title(__('general.saved'))->success()->send();
    }
}
