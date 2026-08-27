<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\CourseResource\Pages;
use App\Models\Book;
use App\Models\Course;
use App\Models\Item;
use App\Models\Period;
use App\Models\InstituteSetting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Database\Eloquent\Builder;

class CourseResource extends Resource
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

    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.courses');
    }

    public static function getModelLabel(): string
    {
        return __('general.course');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.courses');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.course_details'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label(__('general.course_name'))->required()->maxLength(255),
                        Select::make('program_type_id')->native(false)
                            ->label(__('general.program_type'))
                            ->options(fn (): array => \App\Models\ProgramType::query()->pluck('name', 'id')->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label(__('general.name'))->required(),
                                TextInput::make('months_count')->label(__('general.months_count'))->numeric()->maxValue(120)->required()->minValue(1),
                                Toggle::make('is_active')->label(__('general.active'))->default(true),
                            ])
                            ->createOptionModalHeading(__('general.program_type')),
                        Select::make('teacher_id')->native(false)
                            ->label(__('general.teacher'))
                            ->options(fn (): array => \App\Models\Staff::query()->where('status', 'active')->where('is_teacher', true)->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('months')
                            ->label(__('general.months'))
                            ->numeric()->maxValue(999999999999)
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(1),
                        MoneyInput::make('price')
                            ->label(__('general.course_price'))
                            ->required()
                            ->minValue(0)
                            ->suffix(__('general.currency')),
                        TextInput::make('capacity')
                            ->label(__('general.capacity'))
                            ->numeric()->maxValue(999999999999)
                            ->minValue(0)
                            ->helperText(__('general.capacity_hint')),
                        Toggle::make('is_active')->label(__('general.active'))->default(true),
                    ]),
Section::make(__('general.grading_structure'))
                    ->columns(1)
                    ->schema([
                        TextInput::make('full_mark')
                            ->label(__('general.full_mark'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('general.full_mark_hint')),
                        TextInput::make('success_marks')
                            ->label(__('general.success_marks'))
                            ->numeric()
                            ->minValue(0)
                            ->helperText(__('general.success_marks_hint'))
                            ->rule(function (\Filament\Forms\Get $get) {
                                return function (string $attribute, ?string $value, \Closure $fail) use ($get): void {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $fullMark = (float) ($get('full_mark') ?? 0);

                                    if ($fullMark > 0 && (float) $value > $fullMark) {
                                        $fail(__('general.success_marks_out_of_range', [
                                            'max' => number_format($fullMark),
                                        ]));
                                    }
                                };
                            }),
                        Repeater::make('grading_schema')
                            ->label(__('general.grading_schema'))
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('general.name'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('max')
                                    ->label(__('general.max_score'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->rule(function (\Filament\Forms\Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    $fullMark = (float) $get('full_mark');
                                    if (!$fullMark) return;
                                    
                                    $sum = 0;
                                    foreach ((array) $value as $item) {
                                        $sum += (float) ($item['max'] ?? 0);
                                    }
                                    
                                    if (abs($sum - $fullMark) > 0.01) {
                                        $fail(__('general.grading_sum_mismatch', ['sum' => $sum, 'full_mark' => $fullMark]));
                                    }
                                };
                            }),
                    ]),
                \Filament\Forms\Components\Textarea::make('description')
                    ->label(__('general.description'))
                    ->rows(3)
                    ->columnSpanFull(),
                Section::make(__('general.required_supplies'))
                    ->description(__('general.required_supplies_hint'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('required_supplies')
                            ->label(__('general.required_supplies'))
                            ->schema([
                                Select::make('type')->native(false)
                                    ->label(__('general.supply_type'))
                                    ->options([
                                        'book' => __('general.supply_type_book'),
                                        'item' => __('general.supply_type_item'),
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('book')
                                    ->afterStateUpdated(fn (Set $set) => $set('supply_id', null)),
                                Select::make('supply_id')->native(false)
                                    ->label(__('general.supply_id'))
                                    ->options(function (Get $get): array {
                                        if ($get('type') === 'book') {
                                            return Book::query()
                                                ->where('is_active', true)
                                                ->get()
                                                ->mapWithKeys(fn (Book $b): array => [
                                                    'book_'.$b->id => $b->title.' — '.number_format((float) $b->sale_price).' '.__('general.currency'),
                                                ])
                                                ->all();
                                        }

                                        return Item::query()
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn (Item $i): array => [
                                                'item_'.$i->id => $i->name.' — '.number_format((float) $i->sale_price).' '.__('general.currency'),
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),
                                TextInput::make('qty')
                                    ->label(__('general.quantity'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(9999)
                                    ->required()
                                    ->default(1),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel(__('general.add')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.course_name'))->searchable()->weight('semibold'),
                TextColumn::make('programType.name')->label(__('general.program_type'))->badge()->color('gray'),
                TextColumn::make('teacher.name')->label(__('general.teacher'))->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('lifecycle_status')
                    ->label(__('general.enrollment_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('general.lifecycle_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        Course::LIFECYCLE_OPEN     => 'success',
                        Course::LIFECYCLE_RUNNING  => 'info',
                        Course::LIFECYCLE_FULL     => 'warning',
                        Course::LIFECYCLE_FINISHED => 'danger',
                        default                    => 'gray',
                    }),
                TextColumn::make('periods.name')
                    ->label(__('general.periods'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('batches_count')
                    ->label(__('general.batches'))
                    ->counts('batches')
                    ->badge()
                    ->color('info'),
                TextColumn::make('months')->label(__('general.months')),
                TextColumn::make('price')
                    ->label(__('general.course_price'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state).' '.__('general.currency'))
                    ->weight('semibold'),
                IconColumn::make('is_active')->label(__('general.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle')
                    ->label(__('general.enrollment_status'))
                    ->native(false)
                    ->options([
                        'open'     => __('general.lifecycle_open'),
                        'full'     => __('general.lifecycle_full'),
                        'inactive' => __('general.lifecycle_inactive'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null) {
                            return $query;
                        }

                        return match ($value) {
                            'open' => $query->where('is_active', true)
                                ->where(fn ($q) => $q
                                    ->whereNull('capacity')
                                    ->orWhere('capacity', '<=', 0)
                                    ->orWhereRaw(
                                        'capacity > (
                                            SELECT COUNT(*) FROM registrations
                                            WHERE registrations.course_id = courses.id
                                            AND registrations.status IN (\'active\', \'suspended\')
                                        )'
                                    )),
                            'inactive' => $query->where('is_active', false),
                            'full' => $query->where('is_active', true)
                                ->whereNotNull('capacity')
                                ->where('capacity', '>', 0)
                                ->whereRaw(
                                    'capacity <= (
                                        SELECT COUNT(*) FROM registrations
                                        WHERE registrations.course_id = courses.id
                                        AND registrations.status IN (\'active\', \'suspended\')
                                    )'
                                ),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('program_type_id')->native(false)
                    ->label(__('general.program_type'))
                    ->relationship('programType', 'name'),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\Action::make('openNewBatch')
                    ->label(__('general.open_new_batch'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('info')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'registrar']) ?? false)
                    ->modalHeading(__('general.open_new_batch'))
                    ->modalDescription(__('general.open_new_batch_hint'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label(__('general.batch_name'))
                            ->default(fn (Course $record): string => \App\Models\CourseBatch::autoName($record->id))
                            ->helperText(__('general.batch_name_auto_hint'))
                            ->maxLength(255),
                        DatePicker::make('enrollment_start')
                            ->label(__('general.enrollment_start'))
                            ->default(fn (Course $record): string => (InstituteSetting::current()->current_month ?? now()->format('Y-m')).'-01')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('enrollment_end')
                            ->label(__('general.enrollment_end'))
                            ->displayFormat('d/m/Y')
                            ->rules([
                                fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $start = $get('enrollment_start');
                                    if ($value !== null && $value !== '' && $start !== null && $start !== '' && $value < $start) {
                                        $fail(__('general.enrollment_end_after_start'));
                                    }
                                },
                            ]),
                        DatePicker::make('start_date')
                            ->label(__('general.course_start_month'))
                            ->default(fn (Course $record): string => now()->startOfMonth()->toDateString())
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('end_date')
                            ->label(__('general.course_end_month'))
                            ->default(fn (Course $record): string => now()->addMonths(1)->startOfMonth()->subDay()->toDateString())
                            ->rules([
                                fn (\Filament\Forms\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $start = $get('start_date');
                                    if ($value !== null && $value !== '' && $start !== null && $start !== '' && $value < $start) {
                                        $fail(__('general.study_end_after_start'));
                                    }
                                },
                            ])
                            ->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\Toggle::make('close_previous_batch')
                            ->label(__('general.close_previous_batch'))
                            ->helperText(__('general.close_previous_batch_hint'))
                            ->default(false),
                        \Filament\Forms\Components\Toggle::make('close_old_registrations')
                            ->label(__('general.close_old_registrations'))
                            ->helperText(__('general.close_old_registrations_hint'))
                            ->default(false),
                    ])
                    ->action(function (Course $record, array $data): void {
                        app(\App\Services\CourseBatchService::class)->startNewBatch(
                            $record,
                            [
                                'name' => $data['name'] ?? null,
                                'enrollment_start' => $data['enrollment_start'] ?? null,
                                'enrollment_end' => $data['enrollment_end'] ?? null,
                                'start_date' => $data['start_date'] ?? null,
                                'end_date' => $data['end_date'] ?? null,
                                'capacity' => $record->capacity,
                                'teacher_id' => $record->teacher_id,
                                'close_previous_batch' => $data['close_previous_batch'] ?? false,
                                'close_old_registrations' => $data['close_old_registrations'] ?? false,
                            ],
                            (int) \Illuminate\Support\Facades\Auth::id(),
                        );

                        \Filament\Notifications\Notification::make()->title(__('general.new_batch_opened'))->success()->send();
                    })
                    ->visible(fn (?Course $record): bool => $record?->is_active),
                Tables\Actions\Action::make('completeCohort')
                    ->label(__('general.complete_cohort'))
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('general.complete_cohort'))
                    ->modalDescription(__('general.complete_cohort_hint'))
                    ->authorize(fn (): bool => static::canDo('result.finalize'))
                    ->action(function (Course $record): void {
                        $result = app(\App\Services\CourseBatchService::class)->completeCourse(
                            $record,
                            (int) \Illuminate\Support\Facades\Auth::id(),
                        );

                        \Filament\Notifications\Notification::make()
                            ->title(__('general.cohort_completed'))
                            ->body(__('general.completed_summary', [
                                'completed' => $result['completed'],
                                'remaining' => $result['remaining'],
                            ]))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (?Course $record): bool => $record?->is_active),
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
            CourseResource\RelationManagers\BatchesRelationManager::class,
            CourseResource\RelationManagers\RegistrationsRelationManager::class,
            CourseResource\RelationManagers\PrerequisitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit'   => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
