<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

/**
 * Simple marks architecture: ONE final mark per student in the batch,
 * judged against the course's full_mark + success_marks. The mark drives
 * grade_total / grades snapshot / result and the "next course" gate.
 */
class BatchMarks extends Page implements HasForms, HasTable
{
    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'registrar', 'teacher'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.batch-marks';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'course_id' => request()->integer('course_id') ?: null,
            'course_batch_id' => request()->integer('course_batch_id') ?: request()->integer('batch') ?: null,
        ]);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public function getTitle(): string
    {
        return __('general.batch_marks_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.batch_marks');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->options(fn (): array => Course::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (\Filament\Forms\Set $set, Get $get): void {
                        $set('course_batch_id', null);
                    }),
                Select::make('course_batch_id')->native(false)
                    ->label(__('general.batch'))
                    ->placeholder(__('general.select_batch'))
                    ->searchable()
                    ->live()
                    ->options(function (Get $get): array {
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
                    ->helperText(__('general.select_course_and_batch')),
            ])
            ->statePath('data');
    }

    #[Computed]
    public function absenceByRegistration(): array
    {
        $batchId = $this->selectedBatchId();

        if ($batchId <= 0) {
            return [];
        }

        return \App\Models\AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.course_batch_id', $batchId)
            ->selectRaw(
                'attendance_records.registration_id,
                 count(*) as sessions,
                 sum(case when attendance_records.status = "absent" then 1 else 0 end) as absent',
            )
            ->groupBy('attendance_records.registration_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->registration_id => [
                    'sessions' => (int) $row->sessions,
                    'absent' => (int) $row->absent,
                ],
            ])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Registration::query()
                ->with(['student', 'course'])
                ->when(
                    $this->selectedBatchId() > 0,
                    fn (Builder $q) => $q->where('course_batch_id', $this->selectedBatchId()),
                    fn (Builder $q) => $q->whereKey(0),
                )
                ->orderBy('student_id'))
            ->columns([
                TextColumn::make('student.code')
                    ->label(__('general.student_code'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('student.name')
                    ->label(__('general.student_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('student.phone')
                    ->label(__('general.phone'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('start_month')->label(__('general.start_month'))->sortable()->toggleable(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        'transferred' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('mark')
                    ->label(__('general.mark'))
                    ->getStateUsing(fn (?Registration $record): ?string => $record?->grade_total === null ? '—' : number_format($record->grade_total)),
                TextColumn::make('grades.grade')
                    ->label(__('general.grade'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('grade_result')
                    ->label(__('general.result'))
                    ->badge()
                    ->formatStateUsing(fn (Registration $record, string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'passed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('absence_warning')
                    ->label(__('general.attendance_barred'))
                    ->getStateUsing(function (?Registration $record): ?string {
                        if ($record === null) {
                            return null;
                        }

                        $summary = $this->absenceByRegistration()[$record->id] ?? null;

                        if ($summary === null || $summary['sessions'] === 0) {
                            return null;
                        }

                        return ($summary['absent'] / $summary['sessions']) * 100 > 25
                            ? __('general.attendance_barred')
                            : null;
                    })
                    ->badge()
                    ->color('danger')
                    ->visible(fn (): bool => $this->absenceByRegistration() !== []),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('enterMark')
                    ->label(fn (): string => __('general.enter_marks'))
                    ->icon('heroicon-m-pencil-square')
                    ->authorize(fn (): bool => static::canDo('grade.enter'))
                    ->visible(fn (?Registration $record): bool => $record?->result_finalized_at === null)
                    ->modalHeading(fn (?Registration $record): string => __('general.enter_mark_for', [
                        'student' => $record?->student?->name ?? __('general.student'),
                    ]))
                    ->form(function (?Registration $record): array {
                        $course = $this->selectedBatchId() > 0
                            ? CourseBatch::query()->find($this->selectedBatchId())?->course
                            : null;
                        $schema = $course?->grading_schema ?? [];
                        $fullMark = (float) ($course?->full_mark ?? 0);

                        if ($schema === []) {
                            return [
                                TextInput::make('total')
                                    ->label(__('general.mark'))
                                    ->rules([
                                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($fullMark): void {
                                            if ($value === null || $value === '') {
                                                return;
                                            }

                                            if (! is_numeric($value)) {
                                                $fail(__('general.mark_not_numeric'));

                                                return;
                                            }

                                            if ($fullMark > 0 && (float) $value > $fullMark) {
                                                $fail(__('general.mark_exceeds_max', ['max' => number_format($fullMark)]));

                                                return;
                                            }

                                            if ((float) $value < 0) {
                                                $fail(__('general.mark_below_min', ['min' => 0]));
                                            }
                                        },
                                    ])
                                    ->helperText(static function () use ($course, $fullMark): ?string {
                                        $success = $course?->successMark();

                                        return $fullMark > 0
                                            ? __('general.full_mark_hint', [
                                                'full_mark' => number_format($fullMark),
                                                'success_marks' => $success !== null ? number_format($success) : '—',
                                            ])
                                            : null;
                                    })
                                    ->default(fn (Registration $record): ?float => $record->grade_total),
                            ];
                        }

                        $fields = [];

                        foreach ($schema as $index => $item) {
                            $label = (string) ($item['label'] ?? '');
                            $max = (float) ($item['max'] ?? 0);

                            $field = TextInput::make($label)
                                ->label($label.' ('.__('general.max_score').': '.$max.')')
                                ->rules([
                                    fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($max): void {
                                        if ($value === null || $value === '') {
                                            return;
                                        }

                                        if (! is_numeric($value)) {
                                            $fail(__('general.mark_not_numeric'));

                                            return;
                                        }

                                        if ($max > 0 && (float) $value > $max) {
                                            $fail(__('general.mark_exceeds_max', ['max' => number_format($max)]));

                                            return;
                                        }

                                        if ((float) $value < 0) {
                                            $fail(__('general.mark_below_min', ['min' => 0]));
                                        }
                                    },
                                ])
                                ->live()
                                ->default(fn (Registration $record): ?float => isset($record->grades[$label]) && is_numeric($record->grades[$label])
                                    ? (float) $record->grades[$label]
                                    : null)
                                ->afterStateUpdated(fn (mixed $state, \Filament\Forms\Set $set): mixed => $set('__trigger', uniqid()));

                            if ($index === 0) {
                                $field->rule(fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $course): void {
                                    $fullMark = (float) ($course?->full_mark ?? 0);

                                    if ($fullMark <= 0) {
                                        return;
                                    }

                                    $sum = 0.0;

                                    foreach (($get('..') ?? []) as $key => $score) {
                                        if ($key !== '__trigger' && is_numeric($score)) {
                                            $sum += (float) $score;
                                        }
                                    }

                                    if ($sum > $fullMark) {
                                        $fail(__('general.total_exceeds_full_mark', [
                                            'total' => number_format($sum),
                                            'full_mark' => number_format($fullMark),
                                        ]));
                                    }
                                });
                            }

                            $fields[] = $field;
                        }

                        return [
                            \Filament\Forms\Components\Group::make($fields)
                                ->statePath('grades'),
                            \Filament\Forms\Components\Placeholder::make('current_total')
                                ->label(__('general.current_total'))
                                ->content(function (\Filament\Forms\Get $get) use ($fullMark): string {
                                    $get('grades.__trigger');
                                    $grades = $get('grades') ?? [];
                                    unset($grades['__trigger']);

                                    $sum = (float) array_sum(array_map('floatval', $grades));

                                    return number_format($sum).' / '.number_format($fullMark);
                                }),
                        ];
                    })
                    ->action(function (Registration $record, array $data): void {
                        if (isset($data['grades'])) {
                            $record->saveGradeComponents($data['grades'], (int) auth()->id());
                        } else {
                            $value = $data['total'] !== null && $data['total'] !== '' ? (float) $data['total'] : null;

                            $record->saveGrade($value, (int) auth()->id());
                        }

                        Notification::make()->title(__('general.grade_saved'))->success()->send();
                    }),
                \Filament\Tables\Actions\Action::make('printCertificate')
                    ->label(__('general.certificate'))
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->url(fn (Registration $record): string => route('certificates.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (?Registration $record): bool => ($record?->grades['passed'] ?? false) === true),
            ])
            ->emptyStateHeading(__('general.batch_students'))
            ->emptyStateDescription(__('general.select_course_and_batch'));
    }

    protected function getHeaderActions(): array
    {
        $batch = $this->selectedBatchId() > 0 ? CourseBatch::find($this->selectedBatchId()) : null;

        return [
            Action::make('reopenBatch')
                ->label(__('general.reopen_batch'))
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('general.reopen_batch'))
                ->modalDescription(__('general.reopen_batch_confirm'))
                ->form([
                    TextInput::make('reason')
                        ->label(__('general.reopen_reason'))
                        ->required()
                        ->maxLength(255),
                ])
                ->authorize(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                ->action(function (array $data) use ($batch): void {
                    if ($batch === null) {
                        return;
                    }

                    try {
                        app(\App\Services\CourseBatchService::class)->reopen(
                            $batch,
                            (int) auth()->id(),
                            $data['reason'],
                        );

                        Notification::make()
                            ->title(__('general.reopen_batch_done'))
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (): bool => $this->selectedBatchId() > 0
                    && CourseBatch::query()->find($this->selectedBatchId())?->finished_at !== null),
            Action::make('printMarks')
                ->label(__('general.print_marks'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): ?string => $batch !== null ? route('marks.batch.print', $batch) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $batch !== null),
            Action::make('exportMarks')
                ->label(__('general.export_marks'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (): ?string => $batch !== null ? route('marks.batch.export', $batch) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $batch !== null),
            Action::make('printCertificates')
                ->label(__('general.print_certificates'))
                ->icon('heroicon-o-academic-cap')
                ->color('primary')
                ->url(fn (): ?string => $batch !== null ? route('certificates.batch.print', $batch) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $batch !== null),
        ];
    }

    private function selectedBatchId(): int
    {
        return (int) ($this->data['course_batch_id'] ?? 0);
    }
}
