<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\TeachingSessionResource\Pages;
use App\Models\AttendanceSession;
use App\Models\CourseBatch;
use App\Models\Period;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\TeachingSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeachingSessionResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'accountant', 'teacher'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = TeachingSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.teaching_sessions');
    }

    public static function getModelLabel(): string
    {
        return __('general.teaching_session');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.teaching_sessions');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('course_batch_id')
                            ->label(__('general.course_batch'))
                            ->options(CourseBatch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state) {
                                    $batch = CourseBatch::find($state);
                                    if ($batch) {
                                        $primaryTeacherId = $batch->teacher_id;
                                        if (! $primaryTeacherId) {
                                            $activeAssignment = TeacherAssignment::query()
                                                ->where('course_batch_id', $batch->id)
                                                ->where('is_active', true)
                                                ->first();
                                            $primaryTeacherId = $activeAssignment?->staff_id;
                                        }

                                        if ($primaryTeacherId) {
                                            $set('primary_teacher_id', $primaryTeacherId);
                                            $set('actual_teacher_id', $primaryTeacherId);
                                        }

                                        if ($batch->daily_hours > 0) {
                                            $set('planned_hours', $batch->daily_hours);
                                            $set('actual_hours', $batch->daily_hours);
                                        }
                                    }
                                }
                            }),
                        Select::make('period_id')
                            ->label(__('general.period'))
                            ->options(fn (): array => Period::query()->get()->mapWithKeys(fn (Period $period): array => [
                                $period->id => $period->option_label,
                            ])->all())
                            ->searchable()
                            ->nullable(),
                        DatePicker::make('date')
                            ->label(__('general.date'))
                            ->default(now())
                            ->required(),
                        Select::make('primary_teacher_id')
                            ->label(__('general.primary_teacher'))
                            ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                if (! $get('actual_teacher_id')) {
                                    $set('actual_teacher_id', $state);
                                }
                            }),
                        Select::make('actual_teacher_id')
                            ->label(__('general.actual_teacher'))
                            ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $primaryId = $get('primary_teacher_id');
                                if ($primaryId && (int) $state !== (int) $primaryId) {
                                    $set('status', 'substituted');
                                }
                            }),
                        Select::make('status')
                            ->label(__('general.status'))
                            ->options([
                                'completed' => __('general.status_completed'),
                                'substituted' => __('general.status_substituted'),
                                'cancelled' => __('general.status_cancelled'),
                                'postponed' => __('general.status_postponed'),
                            ])
                            ->default('completed')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                if ($state === 'cancelled') {
                                    $set('actual_hours', 0);
                                } elseif (in_array($state, ['completed', 'substituted'], true) && (float) $get('actual_hours') === 0.0) {
                                    $planned = (float) $get('planned_hours');
                                    $set('actual_hours', $planned > 0 ? $planned : 2.0);
                                }
                            }),
                        TextInput::make('planned_hours')
                            ->label(__('general.planned_hours'))
                            ->numeric()
                            ->default(2.00)
                            ->required(),
                        TextInput::make('actual_hours')
                            ->label(__('general.actual_hours'))
                            ->numeric()
                            ->default(2.00)
                            ->required(),
                        TextInput::make('cancellation_reason')
                            ->label(__('general.cancellation_reason'))
                            ->visible(fn (Get $get): bool => $get('status') === 'cancelled')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('general.notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('general.date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('courseBatch.name')
                    ->label(__('general.course_batch'))
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('primaryTeacher.name')
                    ->label(__('general.primary_teacher'))
                    ->searchable(),
                TextColumn::make('actualTeacher.name')
                    ->label(__('general.actual_teacher'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'substituted' => 'info',
                        'cancelled' => 'danger',
                        'postponed' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('actual_hours')
                    ->label(__('general.actual_hours'))
                    ->numeric(2)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_batch_id')
                    ->label(__('general.course_batch'))
                    ->options(CourseBatch::query()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('actual_teacher_id')
                    ->label(__('general.actual_teacher'))
                    ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'completed' => __('general.status_completed'),
                        'substituted' => __('general.status_substituted'),
                        'cancelled' => __('general.status_cancelled'),
                        'postponed' => __('general.status_postponed'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachingSessions::route('/'),
            'create' => Pages\CreateTeachingSession::route('/create'),
            'edit' => Pages\EditTeachingSession::route('/{record}/edit'),
        ];
    }
}
