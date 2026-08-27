<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Registration;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class EnrollmentReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.enrollment_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.reports.enrollment';

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
        return __('general.enrollment_report');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('month')->native(false)
                    ->label(__('general.month'))
                    ->options(fn (): array => Registration::query()
                        ->whereNotNull('start_month')
                        ->distinct()
                        ->orderByDesc('start_month')
                        ->pluck('start_month', 'start_month')
                        ->mapWithKeys(fn (string $m): array => [$m => $m])
                        ->all())
                    ->searchable()
                    ->placeholder(__('general.all')),
                Select::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->options(fn (): array => Course::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (\Filament\Forms\Set $set): void {
                        $set('course_batch_id', null);
                    }),
                Select::make('course_batch_id')->native(false)
                    ->label(__('general.batch'))
                    ->options(function (\Filament\Forms\Get $get): array {
                        $courseId = (int) ($get('course_id') ?? 0);

                        if ($courseId <= 0) {
                            return [];
                        }

                        return CourseBatch::query()
                            ->where('course_id', $courseId)
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (CourseBatch $batch): array => [
                                $batch->id => $batch->option_label,
                            ])
                            ->all();
                    })
                    ->searchable(),
                Select::make('status')->native(false)
                    ->label(__('general.status'))
                    ->options([
                        'active' => __('general.active'),
                        'suspended' => __('general.suspended'),
                        'closed' => __('general.closed'),
                        'transferred' => __('general.transfer'),
                    ]),
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

        return app(ReportService::class)->enrollment(
            $data['month'] ?? null,
            $data['course_id'] ?? null,
            $data['course_batch_id'] ?? null,
            $data['status'] ?? null,
        );
    }

    public function table(Table $table): Table
    {
        $data = $this->data;

        return $table
            ->query(Registration::query()
                ->with(['student', 'course', 'batch.periods'])
                ->when($data['month'] ?? null, fn ($q, $month) => $q->where('start_month', $month))
                ->when($data['course_id'] ?? null, fn ($q, $courseId) => $q->where('course_id', $courseId))
                ->when($data['course_batch_id'] ?? null, fn ($q, $batchId) => $q->where('course_batch_id', $batchId))
                ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->orderBy('start_month')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('start_month')->label(__('general.month'))->weight('semibold'),
                TextColumn::make('student.name')->label(__('general.student'))->searchable()->placeholder('—'),
                TextColumn::make('course.name')->label(__('general.course'))->placeholder('—'),
                TextColumn::make('batch.name')
                    ->label(__('general.batch'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('period')
                    ->label(__('general.period'))
                    ->placeholder('—')
                    ->getStateUsing(fn (?Registration $record): ?string => $record?->batch?->periods_label),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('price_snapshot')
                    ->label(__('general.fees'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency')),
                TextColumn::make('discount_amount')
                    ->label(__('general.discount_amount'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (Registration $record): string => (float) $record->discount_amount > 0
                        ? number_format((float) $record->discount_amount).' '.__('general.currency')
                        : '—'),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        $query = array_filter([
            'month' => $this->data['month'] ?? null,
            'course_id' => $this->data['course_id'] ?? null,
            'course_batch_id' => $this->data['course_batch_id'] ?? null,
            'status' => $this->data['status'] ?? null,
        ]);

        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.enrollment.print', $query))
                ->openUrlInNewTab(),
        ];
    }
}
