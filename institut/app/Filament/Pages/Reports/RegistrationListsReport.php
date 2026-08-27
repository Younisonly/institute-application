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
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class RegistrationListsReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.registration_lists_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar', 'teacher'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.reports.registration-lists';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.registration_lists');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->options(fn (): array => Course::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
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

        $rows = app(ReportService::class)->registrationList(
            $data['course_id'] ?? null,
            $data['course_batch_id'] ?? null,
            $data['status'] ?? null,
        );

        return [
            'rows' => $rows,
            'totalBalance' => (float) $rows->sum(fn ($r): float => $r->balance),
        ];
    }

    public function table(Table $table): Table
    {
        $data = $this->data;

        return $table
            ->query(Registration::query()
                ->withTotals()
                ->with(['student', 'course', 'batch.periods'])
                ->when($data['course_id'] ?? null, fn ($q, $courseId) => $q->where('course_id', $courseId))
                ->when($data['course_batch_id'] ?? null, fn ($q, $batchId) => $q->where('course_batch_id', $batchId))
                ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->orderBy('start_month'))
            ->columns([
                TextColumn::make('student.name')->label(__('general.student'))->searchable()->weight('semibold'),
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
                TextColumn::make('start_month')->label(__('general.start_month')),
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
                TextColumn::make('balance')
                    ->label(__('general.balance'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->state(fn (Registration $record): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) $record->balance))
                    ->color(fn (Registration $record): string => (float) $record->balance > 0 ? 'danger' : ((float) $record->balance < 0 ? 'success' : 'gray'))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance($state, true))
                    ),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        $query = array_filter([
            'course_id' => $this->data['course_id'] ?? null,
            'course_batch_id' => $this->data['course_batch_id'] ?? null,
            'status' => $this->data['status'] ?? null,
        ]);

        return [
            Action::make('excel')
                ->label(__('general.export_excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (): string => route('reports.registrations.export', $query))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.registrations.print', $query))
                ->openUrlInNewTab(),
        ];
    }
}
