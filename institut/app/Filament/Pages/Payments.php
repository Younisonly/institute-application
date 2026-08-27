<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\PaymentDetails;
use App\Models\Account;
use App\Models\IncomeCategory;
use App\Models\OtherPeopleTransaction;
use App\Models\OtherPerson;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Services\AccountService;
use App\Services\ReceiptNumberService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Payments extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.payments';

    public ?array $data = [];

    public bool $isSubmitting = false;

    public function mount(): void
    {
        $this->form->fill([
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'party_type' => 'student',
            'student_id' => request('student_id'),
        ]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.payments');
    }

    public static function getModelLabel(): string
    {
        return __('general.payments');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('party_type')->native(false)
                    ->label(__('general.party'))
                    ->options([
                        'student' => __('general.student'),
                        'other' => __('general.other_person'),
                    ])
                    ->default('student')
                    ->live()
                    ->required(),
                Select::make('student_id')->native(false)
                    ->label(__('general.student'))
                    ->options(fn (): array => Student::query()
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'student'),
                Select::make('other_person_id')->native(false)
                    ->label(__('general.other_person'))
                    ->options(fn (): array => OtherPerson::query()->where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'other'),
                Select::make('registration_id')->native(false)
                    ->label(__('general.registration'))
                    ->options(fn (\Filament\Forms\Get $get): array => Registration::query()
                        ->where('student_id', $get('student_id'))
                        ->whereIn('status', ['active', 'suspended'])
                        ->with('course')
                        ->get()
                        ->mapWithKeys(fn (Registration $r): array => [
                            $r->id => $r->course->name.' — '.$r->start_month.' • '.number_format($r->balance).' '.__('general.currency'),
                        ])
                        ->all())
                    ->searchable()
                    ->live()
                    ->required(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'student' && $get('student_id') !== null)
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'student' && $get('student_id') !== null),
                Select::make('income_category_id')->native(false)
                    ->label(__('general.income_category'))
                    ->options(fn (): array => IncomeCategory::query()->where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'other'),
                MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y')->required(),
                ...PaymentDetails::fields(),
                Select::make('income_account_id')->native(false)
                    ->label(__('general.on_account'))
                    ->options(fn (): array => Account::query()->ofType('income')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->code . ' — ' . $a->name])->all())
                    ->default(fn (): int => app(AccountService::class)->account(AccountService::CODE_INCOME_COURSE_FEES)->id)
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('party_type') === 'student'),
                TextInput::make('description')->label(__('general.description'))->maxLength(255),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('general.record_payment'))
                ->disabled(fn (): bool => $this->isSubmitting)
                ->submit('savePayment'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StudentTransaction::query()
                ->where('type', 'payment')
                ->whereNull('voided_at')
                ->latest('date')
                ->latest('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y')->sortable(),
                TextColumn::make('student.name')->label(__('general.student'))->searchable()->weight('semibold')->placeholder('—'),
                TextColumn::make('receipt_no')->label(__('general.receipt_no'))->placeholder('—'),
                TextColumn::make('method')
                    ->label(__('general.method'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.method_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'transfer' => 'info',
                        'cheque' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' '.__('general.currency')),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('refund')
                    ->label(__('general.record_refund'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label(__('general.amount'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function (StudentTransaction $record): float {
                                $alreadyRefunded = (float) $record->refunds()->sum('amount');
                                return max(0, (float) $record->amount - $alreadyRefunded);
                            })
                            ->default(function (StudentTransaction $record): float {
                                $alreadyRefunded = (float) $record->refunds()->sum('amount');
                                return max(0, (float) $record->amount - $alreadyRefunded);
                            }),
                        \Filament\Forms\Components\DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y'),
                        ...\App\Filament\Forms\Components\PaymentDetails::fields(),
                        \Filament\Forms\Components\TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (StudentTransaction $record, array $data): void {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data): void {
                            StudentTransaction::create([
                                'student_id' => $record->student_id,
                                'registration_id' => $record->registration_id,
                                'type' => 'refund',
                                'amount' => $data['amount'],
                                'date' => $data['date'],
                                'method' => $data['method'] ?? 'cash',
                                'bank_id' => $data['bank_id'] ?? null,
                                'wallet_id' => $data['wallet_id'] ?? null,
                                'transaction_ref' => $data['transaction_ref'] ?? null,
                                'income_account_id' => $record->income_account_id,
                                'description' => $data['description'] ?? null,
                                'original_transaction_id' => $record->id,
                                'receipt_no' => app(\App\Services\ReceiptNumberService::class)->next(),
                                'created_by' => \Illuminate\Support\Facades\Auth::id(),
                            ]);
                        });
                        \Filament\Notifications\Notification::make()->title(__('general.refund_recorded'))->success()->send();
                    })
                    ->visible(function (StudentTransaction $record): bool {
                        $alreadyRefunded = (float) $record->refunds()->sum('amount');
                        return $record->type === 'payment' && $record->amount > $alreadyRefunded;
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->paginated(false)
            ->poll(null)
            ->striped();
    }

    public function savePayment(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->isSubmitting = true;

        try {
            $data = $this->form->getState();

        DB::transaction(function () use ($data): void {
            if ($data['party_type'] === 'student') {
                if (empty($data['student_id'])) {
                    throw ValidationException::withMessages(['student_id' => __('general.field_required')]);
                }

                if (! empty($data['registration_id'])) {
                    $reg = Registration::query()->withTotals()->find($data['registration_id']);
                    $balance = max(0, (float) ($reg?->balance ?? 0));
                } else {
                    $student = Student::query()->withBalance()->find($data['student_id']);
                    $balance = max(0, (float) ($student?->balance ?? 0));
                }

                if ((float) $data['amount'] > $balance) {
                    throw ValidationException::withMessages(['amount' => __('general.payment_exceeds_balance')]);
                }

                StudentTransaction::create([
                    'student_id' => $data['student_id'],
                    'registration_id' => $data['registration_id'] ?? null,
                    'type' => 'payment',
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'method' => $data['method'],
                    'bank_id' => $data['bank_id'] ?? null,
                    'wallet_id' => $data['wallet_id'] ?? null,
                    'transaction_ref' => $data['transaction_ref'] ?? null,
                    'income_account_id' => $data['income_account_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'receipt_no' => app(ReceiptNumberService::class)->next(),
                    'created_by' => Auth::id(),
                ]);
            } else {
                OtherPeopleTransaction::create([
                    'other_person_id' => $data['other_person_id'],
                    'type' => 'in',
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'method' => $data['method'],
                    'bank_id' => $data['bank_id'] ?? null,
                    'wallet_id' => $data['wallet_id'] ?? null,
                    'transaction_ref' => $data['transaction_ref'] ?? null,
                    'income_category_id' => $data['income_category_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'receipt_no' => app(ReceiptNumberService::class)->next(),
                    'created_by' => Auth::id(),
                ]);
            }
        });

        $this->form->fill(['date' => now()->format('Y-m-d'), 'method' => 'cash']);
        Notification::make()->title(__('general.payment_recorded'))->success()->send();
        } finally {
            $this->isSubmitting = false;
        }
    }
}
