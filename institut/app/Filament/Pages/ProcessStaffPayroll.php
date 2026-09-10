<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MonthPicker;
use App\Filament\Forms\Components\PaymentDetails;
use App\Filament\Resources\StaffResource;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffTransaction;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ProcessStaffPayroll extends Page implements HasForms, HasTable
{
    use HasRbac;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.process-staff-payroll';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['month' => now()->format('Y-m')]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.process_salaries');
    }

    public function getTitle(): string
    {
        return __('general.process_staff_payroll');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                MonthPicker::make('month')
                    ->label(__('general.month'))
                    ->default(now()->format('Y-m'))
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('filter')
                ->label(__('general.apply'))
                ->submit('applyFilters'),
        ];
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    public function selectedMonth(): string
    {
        return substr((string) ($this->data['month'] ?? now()->format('Y-m')), 0, 7);
    }

    public function getReportData(): array
    {
        return app(ReportService::class)->salarySheet($this->selectedMonth());
    }

    public function table(Table $table): Table
    {
        $month = $this->selectedMonth();
        $reportData = $this->getReportData();
        $rows = collect($reportData['rows'])->mapWithKeys(fn (array $row): array => [$row['staff']->id => $row]);

        return $table
            ->query(Staff::query()->where('status', 'active')->with('jobTitle')->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.staff_member'))
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('jobTitle.name')
                    ->label(__('general.job_title'))
                    ->placeholder('—'),
                TextColumn::make('salary_type')
                    ->label(__('general.salary_type'))
                    ->badge()
                    ->formatStateUsing(fn (Staff $record): string => match ($record->salary_type) {
                        'monthly' => __('general.monthly'),
                        'percentage' => __('general.percentage').' '.number_format((float) $record->percentage_value).'%',
                        default => __('general.per_hour'),
                    })
                    ->color(fn (Staff $record): string => match ($record->salary_type) {
                        'monthly' => 'primary',
                        'percentage' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('hours_worked')
                    ->label(__('general.today_hours'))
                    ->state(function (Staff $record) use ($month): string {
                        if ($record->salary_type !== 'per_hour') {
                            return '—';
                        }
                        $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
                        $to = $from->endOfMonth();
                        $hours = (float) StaffAttendance::query()
                            ->where('staff_id', $record->id)
                            ->whereBetween('date', [$from, $to])
                            ->whereIn('status', ['present', 'late'])
                            ->sum('hours_worked');

                        return number_format($hours, 2).' '.__('general.hour');
                    }),
                TextColumn::make('calculated_amount')
                    ->label(__('general.amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('semibold')
                    ->state(function (Staff $record) use ($rows): string {
                        $row = $rows->get($record->id) ?? [];
                        $amt = (float) ($row['amount'] ?? 0);

                        return number_format($amt).' '.__('general.currency');
                    })
                    ->color(function (Staff $record) use ($rows): string {
                        $row = $rows->get($record->id) ?? [];
                        $amt = (float) ($row['amount'] ?? 0);

                        return $amt > 0 ? 'success' : 'gray';
                    }),
                TextColumn::make('outstanding_advance')
                    ->label(__('general.outstanding_advance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('semibold')
                    ->formatStateUsing(fn (Staff $record): string => number_format($record->outstanding_advance).' '.__('general.currency'))
                    ->color(fn (Staff $record): string => $record->outstanding_advance > 0 ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(function (Staff $record) use ($rows, $month): string {
                        $period = \App\Models\StaffPayrollPeriod::query()
                            ->where('staff_id', $record->id)
                            ->where('salary_month', $month)
                            ->first();

                        if ($period) {
                            return match ($period->status) {
                                'paid' => __('general.paid_this_month'),
                                'partially_paid' => __('general.partially_paid'),
                                'approved' => __('general.posted_to_employee_account'),
                                default => __('general.pending_payroll_approval'),
                            };
                        }

                        $row = $rows->get($record->id) ?? [];
                        $amt = (float) ($row['amount'] ?? 0);

                        return $amt > 0 ? __('general.pending_payroll_approval') : __('general.no_entitlements_due');
                    })
                    ->color(function (Staff $record) use ($rows, $month): string {
                        $period = \App\Models\StaffPayrollPeriod::query()
                            ->where('staff_id', $record->id)
                            ->where('salary_month', $month)
                            ->first();

                        if ($period) {
                            return match ($period->status) {
                                'paid' => 'success',
                                'partially_paid' => 'warning',
                                'approved' => 'info',
                                default => 'gray',
                            };
                        }

                        $row = $rows->get($record->id) ?? [];
                        $amt = (float) ($row['amount'] ?? 0);

                        return $amt > 0 ? 'warning' : 'gray';
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve_payroll')
                    ->label(__('general.approve_payroll'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->slideOver()
                    ->modalHeading(fn (Staff $record): string => __('general.approve_payroll').' — '.$record->name)
                    ->form(function (Staff $record) use ($rows, $month): array {
                        $row = $rows->get($record->id) ?? [];
                        $suggestedAmount = (float) ($row['amount'] ?? 0);

                        $schema = [];

                        $existingPeriods = \App\Models\StaffPayrollPeriod::query()
                            ->where('staff_id', $record->id)
                            ->where('salary_month', $month)
                            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
                            ->get();

                        if ($existingPeriods->isNotEmpty()) {
                            $totalAccrued = $existingPeriods->sum('net_salary');
                            $totalRemaining = $existingPeriods->sum(fn ($p) => $p->remaining_payable);

                            $schema[] = Placeholder::make('existing_accrual_warning')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 border border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800">'.
                                    '⚠️ <strong>'.__('general.existing_accrual_warning_title').':</strong> '.
                                    __('general.existing_accrual_warning_desc', [
                                        'month' => $month,
                                        'accrued' => number_format($totalAccrued).' '.__('general.currency'),
                                        'remaining' => number_format($totalRemaining).' '.__('general.currency'),
                                    ]).
                                    '</div>'
                                ));
                        }

                        $schema[] = Placeholder::make('accrual_notice')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 p-3 text-sm text-info-800 dark:bg-info-950 dark:text-info-200">'.
                                __('general.salary_accrual_info_notice').
                                '</div>'
                            ));

                        if ($record->outstanding_advance > 0) {
                            $schema[] = Placeholder::make('advance_warning')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200">'.
                                    __('general.outstanding_advance_warning').' <strong>'.number_format($record->outstanding_advance).' '.__('general.currency').'</strong>'.
                                    '</div>'
                                ));
                        }

                        $schema[] = TextInput::make('salary_amount')
                            ->label(__('general.amount'))
                            ->numeric()
                            ->default($suggestedAmount)
                            ->required()
                            ->minValue(0)
                            ->helperText(__('general.salary_amount_edit_hint'));

                        $schema[] = Textarea::make('notes')
                            ->label(__('general.notes'))
                            ->columnSpanFull()
                            ->helperText(__('general.salary_notes_hint'));

                        return $schema;
                    })
                    ->action(function (Staff $record, array $data): void {
                        $month = $this->selectedMonth();
                        $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
                        $to = $from->endOfMonth();
                        $amount = (float) $data['salary_amount'];

                        if ($amount <= 0) {
                            Notification::make()
                                ->title(__('general.cannot_approve_zero_salary_title'))
                                ->body(__('general.cannot_approve_zero_salary_desc', ['name' => $record->name]))
                                ->warning()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record, $month, $from, $to, $data, $amount): void {
                            Staff::query()->lockForUpdate()->find($record->id);

                            $period = \App\Models\StaffPayrollPeriod::create([
                                'staff_id' => $record->id,
                                'salary_month' => $month,
                                'start_date' => $from,
                                'end_date' => $to,
                                'base_salary' => match ($record->salary_type) {
                                    'monthly' => (float) ($record->salary_value ?? 0),
                                    'percentage' => (float) $record->calculatePercentageSalaryForMonth($month),
                                    default => (float) $amount,
                                },
                                'gross_salary' => $amount,
                                'net_salary' => $amount,
                                'status' => 'approved',
                                'approved_at' => now(),
                                'approved_by' => Auth::id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            app(\App\Services\FinancePostingService::class)->postPayrollApproval($period);
                        });

                        if ($record->user) {
                            Notification::make()
                                ->title(__('general.payroll_approved_successfully_title'))
                                ->body(__('general.payroll_approved_successfully_desc', [
                                    'name' => $record->name,
                                    'amount' => number_format($amount).' '.__('general.currency'),
                                ]))
                                ->success()
                                ->sendToDatabase($record->user);
                        }

                        Notification::make()
                            ->title(__('general.payroll_approved_successfully_title'))
                            ->body(__('general.payroll_approved_successfully_desc', [
                                'name' => $record->name,
                                'amount' => number_format($amount).' '.__('general.currency'),
                            ]))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_payroll_records')
                    ->label(__('general.payroll_records'))
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Staff $record): string => StaffResource::getUrl('view', ['record' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('pay_selected')
                    ->label(__('general.approve_all_payrolls'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Placeholder::make('accrual_notice')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 p-3 text-sm text-info-800 dark:bg-info-950 dark:text-info-200">'.
                                __('general.salary_accrual_info_notice').
                                '</div>'
                            )),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                        $month = $this->selectedMonth();
                        $report = app(ReportService::class)->salarySheet($month);
                        $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
                        $to = $from->endOfMonth();
                        $rows = collect($report['rows'])->mapWithKeys(fn (array $row): array => [$row['staff']->id => $row]);

                        $approvedCount = 0;
                        $zeroCount = 0;
                        $alreadyApprovedCount = 0;

                        DB::transaction(function () use ($records, $rows, $month, $from, $to, $data, &$approvedCount, &$zeroCount, &$alreadyApprovedCount): void {
                            foreach ($records as $staff) {
                                $row = $rows->get($staff->id) ?? [];
                                $amount = (float) ($row['amount'] ?? 0);
                                if ($amount <= 0) {
                                    $zeroCount++;

                                    continue;
                                }

                                Staff::query()->lockForUpdate()->find($staff->id);

                                $existingPeriod = \App\Models\StaffPayrollPeriod::query()
                                    ->where('staff_id', $staff->id)
                                    ->where('salary_month', $month)
                                    ->first();

                                if ($existingPeriod && in_array($existingPeriod->status, ['approved', 'partially_paid', 'paid'])) {
                                    $alreadyApprovedCount++;

                                    continue;
                                }

                                $period = \App\Models\StaffPayrollPeriod::updateOrCreate(
                                    [
                                        'staff_id' => $staff->id,
                                        'salary_month' => $month,
                                    ],
                                    [
                                        'start_date' => $from,
                                        'end_date' => $to,
                                        'base_salary' => match ($staff->salary_type) {
                                            'monthly' => (float) ($staff->salary_value ?? 0),
                                            'percentage' => (float) $staff->calculatePercentageSalaryForMonth($month),
                                            default => (float) $amount,
                                        },
                                        'gross_salary' => $amount,
                                        'net_salary' => $amount,
                                        'status' => 'approved',
                                        'approved_at' => now(),
                                        'approved_by' => Auth::id(),
                                    ]
                                );

                                app(\App\Services\FinancePostingService::class)->postPayrollApproval($period);

                                $approvedCount++;
                            }
                        });

                        if ($approvedCount > 0) {
                            $details = [];
                            if ($zeroCount > 0) {
                                $details[] = __('general.bulk_skipped_zero_salaries', ['count' => $zeroCount]);
                            }
                            if ($alreadyApprovedCount > 0) {
                                $details[] = __('general.bulk_skipped_already_approved', ['count' => $alreadyApprovedCount]);
                            }
                            $body = ! empty($details) ? implode(' • ', $details) : null;

                            Notification::make()
                                ->title(__('general.bulk_payrolls_approved_title', ['count' => $approvedCount]))
                                ->body($body)
                                ->success()
                                ->send();

                            return;
                        }

                        if ($alreadyApprovedCount > 0 && $zeroCount === 0) {
                            Notification::make()->title(__('general.all_selected_already_approved'))->warning()->send();
                        } elseif ($zeroCount > 0 && $alreadyApprovedCount === 0) {
                            Notification::make()->title(__('general.all_selected_zero_due'))->warning()->send();
                        } else {
                            Notification::make()->title(__('general.no_eligible_salaries_to_process'))->warning()->send();
                        }
                    }),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pay_all')
                ->label(__('general.approve_all_payrolls'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                ->form([
                    Placeholder::make('accrual_notice')
                        ->label('')
                        ->content(new HtmlString(
                            '<div class="rounded-lg bg-info-50 p-3 text-sm text-info-800 dark:bg-info-950 dark:text-info-200">'.
                            __('general.salary_accrual_info_notice').
                            '</div>'
                        )),
                    Textarea::make('notes')
                        ->label(__('general.notes'))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $month = $this->selectedMonth();
                    $report = app(ReportService::class)->salarySheet($month);
                    $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
                    $to = $from->endOfMonth();

                    $approvedCount = 0;
                    $zeroCount = 0;
                    $alreadyApprovedCount = 0;

                    DB::transaction(function () use ($report, $month, $from, $to, $data, &$approvedCount, &$zeroCount, &$alreadyApprovedCount): void {
                        foreach ($report['rows'] as $row) {
                            $amount = (float) ($row['amount'] ?? 0);
                            if ($amount <= 0) {
                                $zeroCount++;

                                continue;
                            }

                            $staff = $row['staff'];
                            Staff::query()->lockForUpdate()->find($staff->id);

                            $existingPeriod = \App\Models\StaffPayrollPeriod::query()
                                ->where('staff_id', $staff->id)
                                ->where('salary_month', $month)
                                ->first();

                            if ($existingPeriod && in_array($existingPeriod->status, ['approved', 'partially_paid', 'paid'])) {
                                $alreadyApprovedCount++;

                                continue;
                            }

                            $period = \App\Models\StaffPayrollPeriod::updateOrCreate(
                                [
                                    'staff_id' => $staff->id,
                                    'salary_month' => $month,
                                ],
                                [
                                    'start_date' => $from,
                                    'end_date' => $to,
                                    'base_salary' => match ($staff->salary_type) {
                                        'monthly' => (float) ($staff->salary_value ?? 0),
                                        'percentage' => (float) $staff->calculatePercentageSalaryForMonth($month),
                                        default => (float) $amount,
                                    },
                                    'gross_salary' => $amount,
                                    'net_salary' => $amount,
                                    'status' => 'approved',
                                    'approved_at' => now(),
                                    'approved_by' => Auth::id(),
                                    'notes' => $data['notes'] ?? null,
                                ]
                            );

                            app(\App\Services\FinancePostingService::class)->postPayrollApproval($period);

                            $approvedCount++;
                        }
                    });

                    if ($approvedCount > 0) {
                        $details = [];
                        if ($zeroCount > 0) {
                            $details[] = __('general.bulk_skipped_zero_salaries', ['count' => $zeroCount]);
                        }
                        if ($alreadyApprovedCount > 0) {
                            $details[] = __('general.bulk_skipped_already_approved', ['count' => $alreadyApprovedCount]);
                        }
                        $body = ! empty($details) ? implode(' • ', $details) : null;

                        Notification::make()
                            ->title(__('general.bulk_payrolls_approved_title', ['count' => $approvedCount]))
                            ->body($body)
                            ->success()
                            ->send();

                        return;
                    }

                    if ($alreadyApprovedCount > 0 && $zeroCount === 0) {
                        Notification::make()->title(__('general.all_selected_already_approved'))->warning()->send();
                    } elseif ($zeroCount > 0 && $alreadyApprovedCount === 0) {
                        Notification::make()->title(__('general.all_selected_zero_due'))->warning()->send();
                    } else {
                        Notification::make()->title(__('general.no_eligible_salaries_to_process'))->warning()->send();
                    }
                }),
        ];
    }
}
