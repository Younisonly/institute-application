<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MonthPicker;
use App\Filament\Forms\Components\PaymentDetails;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
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

class SalarySheetReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.salary_sheet_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.reports.salary-sheet';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['month' => now()->format('Y-m')]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.salary_sheet');
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

    public function getReport(): array
    {
        return app(ReportService::class)->salarySheet($this->selectedMonth());
    }

    public function selectedMonth(): string
    {
        return substr((string) ($this->data['month'] ?? now()->format('Y-m')), 0, 7);
    }

    private function storeRecordedHours(int $staffId, array $data): void
    {
        $month = $this->selectedMonth();

        DB::transaction(function () use ($staffId, $data, $month): void {
            $staff = Staff::query()->lockForUpdate()->findOrFail($staffId);

            $alreadyPaid = StaffTransaction::query()
                ->where('staff_id', $staff->id)
                ->where('type', 'salary')
                ->whereNull('voided_at')
                ->where(fn ($q) => $q->where('salary_month', $month)
                    ->orWhere(fn ($q2) => $q2->whereNull('salary_month')->where('reference', $month)))
                ->exists();

            if ($alreadyPaid) {
                Notification::make()->title(__('general.already_paid_this_month'))->warning()->send();

                return;
            }

            $date = CarbonImmutable::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

            StaffTransaction::create([
                'staff_id' => $staff->id,
                'type' => 'salary',
                'amount' => round((float) $data['hours'] * (float) $staff->salary_value, 2),
                'date' => $date,
                'method' => $data['method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'reference' => $month,
                'salary_month' => $month,
                'description' => __('general.hours').' × '.$data['hours'].' — '.$month,
                'rate_snapshot' => $staff->salary_value,
                'hours_snapshot' => $data['hours'],
                'salary_type_snapshot' => 'per_hour',
                'created_by' => Auth::id(),
            ]);

            Notification::make()->title(__('general.hours_recorded'))->success()->send();
        });
    }

    private function hoursForm(): array
    {
        return [
            TextInput::make('hours')
                ->label(__('general.hours'))
                ->numeric()->maxValue(1000000)
                ->required()
                ->minValue(0.5)
                ->step(0.5)
                ->default(1)
                ->helperText(fn (?array $arguments): string => $arguments['staff_id'] ?? null
                    ? __('general.hourly_rate').': '.number_format((float) (Staff::query()->find($arguments['staff_id'])?->salary_value ?? 0)).' '.__('general.currency')
                    : ''),
            ...PaymentDetails::fields(),
        ];
    }

    public function recordHoursAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('recordHours')
            ->label(__('general.record_hours'))
            ->icon('heroicon-o-clock')
            ->color('info')
            ->form($this->hoursForm())
            ->action(function (array $arguments, array $data): void {
                $this->storeRecordedHours((int) ($arguments['staff_id'] ?? 0), $data);
            });
    }

    public function table(Table $table): Table
    {
        $reportData = $this->getReport();
        $rows = collect($reportData['rows'])->mapWithKeys(fn (array $row): array => [$row['staff']->id => $row]);

        return $table
            ->query(Staff::query()->where('status', 'active')->with('jobTitle')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label(__('general.staff_member'))->weight('semibold')->searchable(),
                TextColumn::make('jobTitle.name')->label(__('general.job_title'))->placeholder('—'),
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
                TextColumn::make('amount')
                    ->label(__('general.amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('semibold')
                    ->state(function (Staff $record) use ($rows): string {
                        $row = $rows->get($record->id) ?? [];

                        if ($record->salary_type === 'per_hour') {
                            return number_format((float) $record->salary_value).' '.__('general.currency').'/'.__('general.hour');
                        }

                        return number_format((float) ($row['amount'] ?? 0)).' '.__('general.currency');
                    }),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(function (Staff $record) use ($rows): string {
                        $row = $rows->get($record->id) ?? [];

                        if ($row['paid'] ?? false) {
                            return __('general.paid_this_month');
                        }

                        return $record->salary_type === 'per_hour' ? __('general.hours_pending') : '—';
                    })
                    ->color(function (Staff $record) use ($rows): string {
                        $row = $rows->get($record->id) ?? [];

                        return ($row['paid'] ?? false) ? 'success' : 'gray';
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('recordHours')
                    ->label(__('general.record_hours'))
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->visible(fn (?Staff $record): bool => ! ($rows->get($record?->id)['paid'] ?? false) && $record?->salary_type === 'per_hour')
                    ->form([
                        TextInput::make('hours')
                            ->label(__('general.hours'))
                            ->numeric()->maxValue(1000000)
                            ->required()
                            ->minValue(0.5)
                            ->step(0.5)
                            ->default(1)
                            ->helperText(fn (Staff $record): string => __('general.hourly_rate').': '.number_format((float) $record->salary_value).' '.__('general.currency')),
                        ...PaymentDetails::fields(),
                    ])
                    ->action(function (Staff $record, array $data): void {
                        $this->storeRecordedHours($record->id, $data);
                    }),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordSalaries')
                ->label(__('general.pay_salaries'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false)
                ->modalHeading(__('general.pay_salaries'))
                ->modalDescription(__('general.pay_salaries_confirm'))
                ->form([
                    ...PaymentDetails::fields(),
                ])
                ->action(function (array $data): void {
                    $month = $this->selectedMonth();
                    $report = app(ReportService::class)->salarySheet($month);
                    $date = CarbonImmutable::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

                    DB::transaction(function () use ($report, $month, $date, $data): void {
                        foreach ($report['rows'] as $row) {
                            if ($row['amount'] <= 0) {
                                continue;
                            }

                            $staffId = $row['staff']->id;
                            Staff::query()->lockForUpdate()->find($staffId);

                            $alreadyPaid = StaffTransaction::query()
                                ->where('staff_id', $staffId)
                                ->where('type', 'salary')
                                ->whereNull('voided_at')
                                ->where(fn ($q) => $q->where('salary_month', $month)
                                    ->orWhere(fn ($q2) => $q2->whereNull('salary_month')->where('reference', $month)))
                                ->exists();

                            if ($alreadyPaid) {
                                continue;
                            }

                            StaffTransaction::create([
                                'staff_id' => $staffId,
                                'type' => 'salary',
                                'amount' => $row['amount'],
                                'date' => $date,
                                'method' => $data['method'],
                                'bank_id' => $data['bank_id'] ?? null,
                                'wallet_id' => $data['wallet_id'] ?? null,
                                'transaction_ref' => $data['transaction_ref'] ?? null,
                                'reference' => $month,
                                'salary_month' => $month,
                                'description' => __('general.salary').' — '.$month,
                                'rate_snapshot' => $row['staff']->salary_value,
                                'percentage_snapshot' => $row['staff']->percentage_value,
                                'salary_type_snapshot' => $row['staff']->salary_type,
                                'created_by' => Auth::id(),
                            ]);
                        }
                    });

                    Notification::make()->title(__('general.saved'))->success()->send();
                }),
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.salary-sheet.print', ['month' => $this->selectedMonth()]))
                ->openUrlInNewTab(),
        ];
    }
}
