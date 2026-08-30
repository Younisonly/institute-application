<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\CourseBatchResource\Pages;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Period;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourseBatchResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'registrar'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'registrar'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = CourseBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.batches');
    }

    public static function getModelLabel(): string
    {
        return __('general.batch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.batches');
    }

    public static function form(Form $form): Form
    {
        return $form->schema(self::fields(true));
    }

    /**
     * Form schema. With $withCourse the course picker is included (resource
     * form); without it, the course is preset by the parent relation manager.
     * $course lets the relation manager prefill the auto name.
     */
    public static function fields(bool $withCourse, ?Course $course = null): array
    {
        $details = [
            TextInput::make('name')
                ->label(__('general.batch_name'))
                ->required()
                ->maxLength(255)
                ->helperText(__('general.batch_name_auto_hint'))
                ->default(fn (): ?string => $course !== null ? CourseBatch::autoName($course->id) : null),
            TextInput::make('year')
                ->label(__('general.batch_year'))
                ->placeholder(now()->format('Y'))
                ->default(now()->format('Y'))
                ->integer()
                ->minValue(1900)
                ->maxValue(2100)
                ->maxLength(4)
                ->helperText(__('general.batch_year_hint')),
            TextInput::make('identifier')
                ->label(__('general.batch_id'))
                ->disabled()
                ->dehydrated(false)
                ->placeholder('—')
                ->default(fn (): ?string => $course !== null ? CourseBatch::autoName($course->id) : null)
                ->afterStateHydrated(function (TextInput $component, ?CourseBatch $record): void {
                    if ($record !== null) {
                        $component->state(CourseBatch::autoName(
                            (int) $record->course_id,
                            CourseBatch::sequenceOf((int) $record->course_id, (int) $record->id),
                        ));
                    }
                })
                ->helperText(__('general.batch_id_hint')),
            Select::make('teacher_id')->native(false)
                ->label(__('general.batch_teacher'))
                ->options(fn (): array => Staff::query()
                    ->where('status', 'active')
                    ->where('is_teacher', true)
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            TextInput::make('capacity')
                ->label(__('general.batch_capacity'))
                ->numeric()
                ->minValue(0)
                ->maxValue(999999999)
                ->helperText(__('general.capacity_hint')),
            TextInput::make('daily_hours')
                ->label(__('general.daily_hours'))
                ->numeric()
                ->default(fn (): float => (float) ($course?->hours_per_session ?? 2.00))
                ->required(),
            TextInput::make('total_hours')
                ->label(__('general.total_hours'))
                ->numeric()
                ->default(fn (): int => (int) ($course?->total_planned_hours ?? 30))
                ->required(),
            TextInput::make('break_duration')
                ->label(__('general.break_duration'))
                ->numeric()
                ->default(fn (): int => (int) ($course?->break_duration ?? 0))
                ->suffix('min'),
            \Filament\Forms\Components\CheckboxList::make('working_days')
                ->label(__('general.working_days'))
                ->default(fn (): ?array => $course?->working_days)
                ->options([
                    'sun' => __('general.day_sun'),
                    'mon' => __('general.day_mon'),
                    'tue' => __('general.day_tue'),
                    'wed' => __('general.day_wed'),
                    'thu' => __('general.day_thu'),
                    'fri' => __('general.day_fri'),
                    'sat' => __('general.day_sat'),
                ])
                ->columns(4)
                ->columnSpanFull(),
            MoneyInput::make('fee_schedule')
                ->label(__('general.batch_fee'))
                ->suffix(__('general.currency'))
                ->minValue(0)
                ->helperText(__('general.batch_fee_hint'))
                ->formatStateUsing(fn ($state, ?Model $record): mixed => $record?->fee_schedule['price'] ?? null)
                ->dehydrateStateUsing(fn ($state): ?array => $state === null || $state === '' ? null : ['price' => (float) $state]),
            Select::make('status')->native(false)
                ->label(__('general.batch_status'))
                ->default('open')
                ->options(collect(\App\Models\CourseBatch::STATUSES)
                    ->mapWithKeys(fn (string $status): array => [
                        $status => __("general.batch_status_{$status}"),
                    ])
                    ->all())
                ->visible(fn (string $operation): bool => $operation === 'create'),
            \Filament\Forms\Components\Placeholder::make('status_indicator')
                ->label(__('general.batch_status'))
                ->content(fn (?Model $record): string => $record !== null
                    ? __("general.batch_status_{$record->status}")
                    : '—')
                ->visible(fn (string $operation): bool => $operation !== 'create'),
            DatePicker::make('start_date')
                ->label(__('general.course_start_month'))
                ->default(now()->startOfMonth()->toDateString())
                ->helperText(__('general.batch_date_start_hint'))
                ->after(function (\Filament\Forms\Get $get, \Filament\Forms\Components\Component $component): void {
                    $end = $get('end_date');
                    if ($end === null || $end === '') {
                        return;
                    }

                    $start = $component->getState();
                    if ($start !== null && $start !== '' && $start > $end) {
                        $component->state($end);
                    }
                })
                ->rules([
                    fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $end = $get('end_date');
                        if ($value !== null && $value !== '' && $end !== null && $end !== '' && $value > $end) {
                            $fail(__('general.study_end_after_start'));
                        }
                    },
                ])
                ->displayFormat('d/m/Y'),
            DatePicker::make('end_date')
                ->label(__('general.course_end_month'))
                ->default(now()->addMonths(1)->startOfMonth()->subDay()->toDateString())
                ->helperText(__('general.batch_date_end_hint'))
                ->after(function (\Filament\Forms\Get $get, \Filament\Forms\Components\Component $component): void {
                    $start = $get('start_date');
                    if ($start === null || $start === '') {
                        return;
                    }

                    $end = $component->getState();
                    if ($end !== null && $end !== '' && $end < $start) {
                        $component->state($start);
                    }
                })
                ->rules([
                    fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $start = $get('start_date');
                        if ($value !== null && $value !== '' && $start !== null && $start !== '' && $value < $start) {
                            $fail(__('general.study_end_after_start'));
                        }
                    },
                ])
                ->displayFormat('d/m/Y'),
        ];

        if ($withCourse) {
            array_unshift($details, Select::make('course_id')->native(false)
                ->label(__('general.course'))
                ->options(fn (): array => Course::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $auto = CourseBatch::autoName((int) $state);
                    $set('name', $auto);
                    $set('identifier', $auto);
                }));
        }

        return [
            Section::make(__('general.batch_details'))
                ->columns(2)
                ->schema($details),
            Section::make(__('general.enrollment_window'))
                ->columns(2)
                ->description(__('general.batch_enrollment_window_hint'))
                ->schema([
                    DatePicker::make('enrollment_start')
                        ->label(__('general.enrollment_start'))
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('enrollment_end')
                        ->label(__('general.enrollment_end'))
                        ->displayFormat('d/m/Y')
                        ->rules([
                            fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $start = $get('enrollment_start');
                                if ($value !== null && $value !== '' && $start !== null && $start !== '' && $value < $start) {
                                    $fail(__('general.validation_enrollment_end_after_start'));
                                }
                            },
                        ]),
                ]),
            Section::make(__('general.batch_periods'))
                ->schema([
                    Radio::make('periods')
                        ->label(__('general.batch_periods'))
                        ->required()
                        ->options(fn (): array => Period::query()
                            ->orderBy('start_time')
                            ->get()
                            ->mapWithKeys(fn (Period $period): array => [
                                $period->id => $period->option_label,
                            ])
                            ->all())
                        ->columns(2)
                        ->helperText(__('general.batch_period_single_hint'))
                        ->afterStateHydrated(function (Radio $component, ?CourseBatch $record): void {
                            if ($record !== null) {
                                $component->state($record->periods()->value('periods.id'));
                            }
                        }),
                    Textarea::make('notes')
                        ->label(__('general.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.batch_name'))
                    ->searchable()
                    ->weight('semibold')
                    ->limit(50)
                    ->tooltip(fn (CourseBatch $record): string => (string) $record->name)
                    ->extraAttributes(['class' => 'min-w-64']),
                TextColumn::make('course.name')
                    ->label(__('general.course'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('year')->label(__('general.batch_year'))->badge()->color('info')->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('general.batch_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.batch_status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'scheduled' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('enrollment_window_status')
                    ->label(__('general.enrollment_window_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.enrollment_window_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'open'      => 'success',
                        'upcoming'  => 'info',
                        'closed'    => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('study_period_status')
                    ->label(__('general.study_period_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.study_period_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'running'  => 'success',
                        'upcoming' => 'info',
                        'finished' => 'warning',
                        default    => 'gray',
                    }),
                TextColumn::make('periods.name')
                    ->label(__('general.periods'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('start_date')->label(__('general.course_start_month'))->date('d/m/Y')->placeholder('—')->toggleable(),
                TextColumn::make('end_date')->label(__('general.course_end_month'))->date('d/m/Y')->placeholder('—')->toggleable(),
                TextColumn::make('teacher.name')->label(__('general.teacher'))->placeholder('—')->toggleable(),
                TextColumn::make('registrations_count')
                    ->label(__('general.students'))
                    ->counts('registrations')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('year')->native(false)
                    ->label(__('general.batch_year'))
                    ->options(fn (): array => CourseBatch::query()
                        ->whereNotNull('year')
                        ->distinct()
                        ->orderBy('year', 'desc')
                        ->pluck('year', 'year')
                        ->all()),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\Action::make('recordTeacherAttendance')
                    ->label(__('general.record_teacher_attendance'))
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->form(fn (CourseBatch $record): array => [
                        DatePicker::make('date')
                            ->label(__('general.date'))
                            ->default(now())
                            ->required(),
                        Select::make('status')
                            ->label(__('general.status'))
                            ->options([
                                'present' => __('general.present'),
                                'absent' => __('general.absent'),
                                'late' => __('general.late'),
                                'excused' => __('general.excused'),
                                'cancelled_session' => __('general.cancelled_session'),
                            ])
                            ->default('present')
                            ->required(),
                        TextInput::make('hours_worked')
                            ->label(__('general.hours_worked'))
                            ->numeric()
                            ->default($record->daily_hours ?? 2.00)
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('general.notes')),
                    ])
                    ->action(function (CourseBatch $record, array $data): void {
                        if (! $record->teacher_id) {
                            Notification::make()
                                ->title(__('general.no_teacher_assigned'))
                                ->warning()
                                ->send();
                            return;
                        }

                        StaffAttendance::updateOrCreate(
                            [
                                'staff_id' => $record->teacher_id,
                                'course_batch_id' => $record->id,
                                'date' => $data['date'],
                            ],
                            [
                                'status' => $data['status'],
                                'hours_worked' => $data['hours_worked'],
                                'notes' => $data['notes'] ?? null,
                                'created_by' => Auth::id(),
                            ]
                        );

                        Notification::make()
                            ->title(__('general.saved'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (?CourseBatch $record): bool => $record !== null && $record->teacher_id !== null),
                Tables\Actions\Action::make('completeBatch')
                    ->label(__('general.complete_batch'))
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.complete_batch'))
                    ->modalDescription(__('general.complete_batch_hint'))
                    ->authorize(fn (): bool => static::canDo('result.finalize'))
                    ->action(function (CourseBatch $record): void {
                        $result = app(\App\Services\CourseBatchService::class)->complete(
                            $record,
                            (int) Auth::id(),
                        );

                        Notification::make()
                            ->title(__('general.batch_completed'))
                            ->body(__('general.completed_summary', [
                                'completed' => $result['completed'],
                                'remaining' => $result['remaining'],
                            ]))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (?CourseBatch $record): bool => $record?->finished_at === null),
                Tables\Actions\Action::make('reopenBatch')
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
                    ->authorize(fn (): bool => static::canDo('result.finalize'))
                    ->action(function (CourseBatch $record, array $data): void {
                        try {
                            app(\App\Services\CourseBatchService::class)->reopen(
                                $record,
                                (int) Auth::id(),
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
                    ->visible(fn (?CourseBatch $record): bool => $record?->finished_at !== null),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make()->label(__('general.restore')),
                Tables\Actions\ForceDeleteAction::make()
                    ->label(__('general.force_delete'))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label(__('general.force_delete'))
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CourseBatchResource\RelationManagers\TeacherAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseBatches::route('/'),
            'create' => Pages\CreateCourseBatch::route('/create'),
            'edit' => Pages\EditCourseBatch::route('/{record}/edit'),
        ];
    }
}