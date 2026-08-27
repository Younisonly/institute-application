<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class StudentPaymentHistoryReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.student_payment_history_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.reports.payment-history';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.payment_history_report');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label(__('general.from'))->displayFormat('d/m/Y'),
                DatePicker::make('to')->label(__('general.to'))->displayFormat('d/m/Y'),
                Select::make('student_id')->native(false)
                    ->label(__('general.student'))
                    ->options(fn (): array => Student::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('registration_id')->native(false)
                    ->label(__('general.registration'))
                    ->options(fn (): array => Registration::query()
                        ->when($this->data['student_id'] ?? null, fn ($q) => $q->where('student_id', $this->data['student_id']))
                        ->with(['course'])
                        ->get()
                        ->mapWithKeys(fn (Registration $r): array => [
                            $r->id => $r->course?->name.' — '.$r->start_month,
                        ])
                        ->all())
                    ->searchable()
                    ->visible(fn (): bool => ! empty($this->data['student_id'])),
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
        $data = $this->data;
        $rows = app(ReportService::class)->paymentHistory(
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['student_id'] ?? null,
            $data['registration_id'] ?? null,
        );

        return [
            'rows' => $rows,
            'total' => (float) $rows->sum('amount'),
        ];
    }

    public function table(Table $table): Table
    {
        $data = $this->data;

        return $table
            ->query(StudentTransaction::query()
                ->where('type', 'payment')
                ->whereNull('voided_at')
                ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
                ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
                ->when($data['student_id'] ?? null, fn ($q, $studentId) => $q->where('student_id', $studentId))
                ->when($data['registration_id'] ?? null, fn ($q, $registrationId) => $q->where('registration_id', $registrationId))
                ->with(['student', 'registration.course'])
                ->orderBy('date')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('date')->label(__('general.date'))->date('d/m/Y'),
                TextColumn::make('student.name')->label(__('general.student'))->searchable()->weight('semibold')->placeholder('—'),
                TextColumn::make('registration.course.name')->label(__('general.course'))->placeholder('—'),
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
                    ->weight('bold')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' '.__('general.currency')),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        $query = array_filter([
            'from' => $this->data['from'] ?? null,
            'to' => $this->data['to'] ?? null,
            'student_id' => $this->data['student_id'] ?? null,
            'registration_id' => $this->data['registration_id'] ?? null,
        ]);

        return [
            Action::make('excel')
                ->label(__('general.export_excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (): string => route('reports.payment-history.export', $query))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.payment-history.print', $query))
                ->openUrlInNewTab(),
        ];
    }
}
