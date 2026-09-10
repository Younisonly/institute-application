<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\StaffAttendanceResource\Pages;
use App\Models\CourseBatch;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\AttendanceManagementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffAttendanceResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.staff_attendances');
    }

    public static function getModelLabel(): string
    {
        return __('general.staff_attendance');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.staff_attendances');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'active')
            ->with(['jobTitle', 'attendances']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::getAttendanceFormSchema());
    }

    public static function getAttendanceFormSchema(?Staff $staff = null): array
    {
        return [
            Section::make(__('general.tab_general'))
                ->columns(2)
                ->schema([
                    Select::make('staff_id')
                        ->label(__('general.staff_member'))
                        ->options(Staff::query()->where('status', 'active')->pluck('name', 'id'))
                        ->default($staff?->id)
                        ->disabled((bool) $staff)
                        ->dehydrated()
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('course_batch_id', null);
                            $set('primary_teacher_id', null);
                            $set('actual_teacher_id', $state);
                            $set('planned_hours', 2.00);
                            $set('hours_worked', 2.00);
                            $set('cancellation_reason', null);
                        }),
                    DatePicker::make('date')
                        ->label(__('general.date'))
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()->toDateString())
                        ->required()
                        ->live()
                        ->helperText(function ($state, Get $get) use ($staff): ?string {
                            if (! $state) {
                                return null;
                            }
                            $date = \Carbon\Carbon::parse($state)->startOfDay();
                            $staffId = $get('staff_id') ?? $staff?->id;

                            if ($staffId) {
                                $dateStr = $date->toDateString();
                                $existing = \App\Models\StaffAttendance::query()
                                    ->where('staff_id', $staffId)
                                    ->whereDate('date', $dateStr)
                                    ->first();

                                if ($existing) {
                                    $statusLabel = __("general.{$existing->status}");

                                    return __('general.attendance_already_recorded_hint', ['status' => $statusLabel]);
                                }
                            }

                            if ($date->lt(now()->startOfDay())) {
                                if (! \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['admin', 'accountant', 'registrar'])) {
                                    return __('general.past_date_edit_restricted_to_admin');
                                }

                                return __('general.past_date_warning_hint');
                            }

                            return null;
                        }),
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
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                            if (in_array($state, ['absent', 'cancelled_session'], true)) {
                                $set('hours_worked', 0);
                            } elseif ($state === 'present' && (float) $get('hours_worked') === 0.0) {
                                $batchId = $get('course_batch_id');
                                if ($batchId) {
                                    $batch = CourseBatch::find($batchId);
                                    $set('hours_worked', $batch?->daily_hours ?? 2.0);
                                } else {
                                    $set('hours_worked', 2.0);
                                }
                            }
                        }),
                    Select::make('course_batch_id')
                        ->label(__('general.course_batch'))
                        ->options(function (Get $get) use ($staff): array {
                            $staffId = $get('actual_teacher_id') ?? $get('staff_id') ?? $staff?->id;
                            
                            $runningBatches = CourseBatch::query()
                                ->where('status', 'in_progress')
                                ->with(['teacher', 'course'])
                                ->get();

                            if ($runningBatches->isEmpty()) {
                                return [];
                            }

                            $assignedBatchIds = [];
                            if ($staffId) {
                                $assignedBatchIds = CourseBatch::query()
                                    ->where('status', 'in_progress')
                                    ->where(function ($q) use ($staffId) {
                                        $q->where('teacher_id', $staffId)
                                            ->orWhereHas('teacherAssignments', function ($q2) use ($staffId) {
                                                $q2->where('staff_id', $staffId)->where('is_active', true);
                                            });
                                    })
                                    ->pluck('id')
                                    ->toArray();
                            }

                            $options = [];
                            $sortedBatches = $runningBatches->sortByDesc(fn ($b) => in_array($b->id, $assignedBatchIds));

                            foreach ($sortedBatches as $batch) {
                                $options[$batch->id] = $batch->option_label;
                            }

                            return $options;
                        })
                        ->visible(function (Get $get) use ($staff): bool {
                            $staffId = $get('staff_id') ?? $staff?->id;
                            if (! $staffId) {
                                return false;
                            }

                            return (bool) Staff::find($staffId)?->is_teacher;
                        })
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($staff): void {
                            if ($state) {
                                $batch = CourseBatch::find($state);
                                if ($batch) {
                                    if ($batch->daily_hours > 0) {
                                        $set('hours_worked', $batch->daily_hours);
                                        $set('planned_hours', $batch->daily_hours);
                                    }
                                    $staffId = $get('staff_id') ?? $staff?->id;
                                    $primaryTeacherId = $batch->teacher_id ?? $staffId;
                                    $set('primary_teacher_id', $primaryTeacherId);

                                    if (! $get('actual_teacher_id')) {
                                        $set('actual_teacher_id', $staffId);
                                    }
                                }
                            } else {
                                $set('primary_teacher_id', null);
                            }
                        }),
                    Select::make('primary_teacher_id')
                        ->label(__('general.primary_teacher'))
                        ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                        ->default(fn (Get $get) => $get('staff_id') ?? $staff?->id)
                        ->disabled()
                        ->dehydrated()
                        ->visible(function (Get $get) use ($staff): bool {
                            $staffId = $get('staff_id') ?? $staff?->id;

                            return (bool) Staff::find($staffId)?->is_teacher && (bool) $get('course_batch_id');
                        })
                        ->searchable(),
                    Select::make('actual_teacher_id')
                        ->label(__('general.actual_teacher'))
                        ->options(function (Get $get) use ($staff): array {
                            $staffId = $get('staff_id') ?? $staff?->id;

                            return Staff::query()
                                ->where('is_teacher', true)
                                ->where(function ($q) use ($staffId) {
                                    $q->where('status', 'active');
                                    if ($staffId) {
                                        $q->orWhere('id', $staffId);
                                    }
                                })
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->default(fn (Get $get) => $get('staff_id') ?? $staff?->id)
                        ->visible(function (Get $get) use ($staff): bool {
                            $staffId = $get('staff_id') ?? $staff?->id;

                            return (bool) Staff::find($staffId)?->is_teacher;
                        })
                        ->searchable()
                        ->required(function (Get $get) use ($staff): bool {
                            $staffId = $get('staff_id') ?? $staff?->id;

                            return (bool) Staff::find($staffId)?->is_teacher && (bool) $get('course_batch_id');
                        })
                        ->live()
                        ->helperText(function (Get $get): ?string {
                            $primaryId = $get('primary_teacher_id');
                            $actualId = $get('actual_teacher_id');
                            if ($primaryId && $actualId && (int) $primaryId !== (int) $actualId) {
                                $primaryName = Staff::find($primaryId)?->name ?? '—';

                                return __('general.substitute_teacher_notice', ['primary' => $primaryName]);
                            }

                            return null;
                        }),
                    TextInput::make('planned_hours')
                        ->label(__('general.planned_hours'))
                        ->numeric()
                        ->default(2.00)
                        ->disabled()
                        ->dehydrated()
                        ->visible(function (Get $get) use ($staff): bool {
                            $staffId = $get('staff_id') ?? $staff?->id;

                            return (bool) Staff::find($staffId)?->is_teacher && (bool) $get('course_batch_id');
                        }),
                    TextInput::make('hours_worked')
                        ->label(__('general.hours_worked'))
                        ->numeric()
                        ->default(2.00)
                        ->required(),
                    TextInput::make('cancellation_reason')
                        ->label(__('general.cancellation_reason'))
                        ->visible(fn (Get $get): bool => $get('status') === 'cancelled_session'),
                    Textarea::make('notes')
                        ->label(__('general.notes'))
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name)),
                TextColumn::make('name')
                    ->label(__('general.staff_member'))
                    ->description(fn (Staff $record): string => $record->code ?? "#{$record->id}")
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('jobTitle.name')
                    ->label(__('general.job_title'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('is_teacher')
                    ->label(__('general.staff_type'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('general.teacher') : __('general.employee'))
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                TextColumn::make('today_status')
                    ->label(__('general.today_status'))
                    ->badge()
                    ->state(function (Staff $record): string {
                        $att = $record->attendances()->whereDate('date', now()->toDateString())->first();

                        return $att ? $att->status : 'not_recorded';
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'not_recorded' => __('general.not_recorded'),
                        default => __("general.{$state}"),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'excused' => 'info',
                        'absent' => 'danger',
                        'cancelled_session' => 'gray',
                        'not_recorded' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('today_hours')
                    ->label(__('general.today_hours'))
                    ->state(function (Staff $record): float {
                        $att = $record->attendances()->whereDate('date', now()->toDateString())->first();

                        return $att ? (float) $att->hours_worked : 0.0;
                    })
                    ->numeric(2),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('job_title_id')
                    ->label(__('general.job_title'))
                    ->relationship('jobTitle', 'name'),
                Tables\Filters\SelectFilter::make('is_teacher')
                    ->label(__('general.staff_type'))
                    ->options([
                        '1' => __('general.teacher'),
                        '0' => __('general.employee'),
                    ]),
                Tables\Filters\SelectFilter::make('today_status')
                    ->label(__('general.today_status'))
                    ->options([
                        'present' => __('general.present'),
                        'absent' => __('general.absent'),
                        'late' => __('general.late'),
                        'excused' => __('general.excused'),
                        'cancelled_session' => __('general.cancelled_session'),
                        'not_recorded' => __('general.not_recorded'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        $status = $data['value'];
                        $today = now()->toDateString();

                        if ($status === 'not_recorded') {
                            return $query->whereDoesntHave('attendances', function (Builder $q) use ($today) {
                                $q->whereDate('date', $today);
                            });
                        }

                        return $query->whereHas('attendances', function (Builder $q) use ($today, $status) {
                            $q->whereDate('date', $today)->where('status', $status);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_history')
                    ->label(__('general.view_attendance_history'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Staff $record): string => static::getUrl('employee-history', ['record' => $record])),
                Tables\Actions\Action::make('register_attendance')
                    ->label(__('general.register_attendance'))
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->slideOver()
                    ->modalSubmitActionLabel(__('general.save'))
                    ->form(fn (?Staff $record): array => static::getAttendanceFormSchema($record))
                    ->fillForm(fn (Staff $record): array => [
                        'staff_id' => $record->id,
                        'actual_teacher_id' => $record->id,
                        'date' => now()->toDateString(),
                        'status' => 'present',
                        'hours_worked' => 2.00,
                        'planned_hours' => 2.00,
                    ])
                    ->action(function (Staff $record, array $data): void {
                        try {
                            $data['staff_id'] = $record->id;
                            if (empty($data['actual_teacher_id'])) {
                                $data['actual_teacher_id'] = $record->id;
                            }
                            $att = app(AttendanceManagementService::class)->saveAttendance($data);
                            if ($att->getAttribute('is_closed_payroll_edit')) {
                                Notification::make()
                                    ->title(__('general.attendance_saved_retroactive_notice'))
                                    ->warning()
                                    ->send();
                            } elseif ($att->getAttribute('was_already_recorded')) {
                                Notification::make()
                                    ->title(__('general.attendance_already_recorded_updated'))
                                    ->info()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('general.saved'))
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffAttendances::route('/'),
            'employee-history' => Pages\EmployeeAttendanceHistory::route('/employee/{record}'),
        ];
    }
}
