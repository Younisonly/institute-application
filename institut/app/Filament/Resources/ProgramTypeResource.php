<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\PaymentDetails;
use App\Filament\Resources\ProgramTypeResource\Pages;
use App\Models\Course;
use App\Models\InstituteSetting;
use App\Models\ProgramType;
use App\Models\Student;
use App\Services\RegistrationService;
use Filament\Forms\Components\CheckboxList;
use App\Filament\Forms\Components\MonthPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProgramTypeResource extends Resource
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

    protected static ?string $model = ProgramType::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.program_types');
    }

    public static function getModelLabel(): string
    {
        return __('general.program_type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.program_types');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label(__('general.name'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('code')
                    ->label(__('general.program_code'))
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->helperText(__('general.program_code_hint')),
                TextInput::make('months_count')
                    ->label(__('general.months_count'))
                    ->numeric()->maxValue(999999999999)
                    ->required()
                    ->minValue(1)
                    ->maxValue(120),
                Select::make('study_system')
                    ->label(__('general.study_system'))
                    ->default(\App\Models\ProgramType::STUDY_SYSTEM_ANNUAL)
                    ->options([
                        \App\Models\ProgramType::STUDY_SYSTEM_ANNUAL => __('general.study_system_annual'),
                        \App\Models\ProgramType::STUDY_SYSTEM_SEMESTER => __('general.study_system_semester'),
                    ]),
                Select::make('status')
                    ->label(__('general.program_status'))
                    ->default(\App\Models\ProgramType::STATUS_ACTIVE)
                    ->options([
                        \App\Models\ProgramType::STATUS_ACTIVE => __('general.program_status_active'),
                        \App\Models\ProgramType::STATUS_ARCHIVED => __('general.program_status_archived'),
                    ])
                    ->helperText(__('general.program_status_hint')),
                Toggle::make('is_active')->label(__('general.active'))->default(true),
                \Filament\Forms\Components\Textarea::make('notes')
                    ->label(__('general.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('code')
                    ->label(__('general.program_code'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('study_system')
                    ->label(__('general.study_system'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => __("general.study_system_".($state ?? 'annual'))),
                TextColumn::make('status')
                    ->label(__('general.program_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.program_status_{$state}"))
                    ->color(fn (string $state): string => $state === 'archived' ? 'danger' : 'success'),
                TextColumn::make('courses_count')
                    ->label(__('general.courses'))
                    ->counts('courses')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label(__('general.active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.program_status'))
                    ->options([
                        \App\Models\ProgramType::STATUS_ACTIVE => __('general.program_status_active'),
                        \App\Models\ProgramType::STATUS_ARCHIVED => __('general.program_status_archived'),
                    ]),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\Action::make('registerStudent')
                    ->label(__('general.register_student'))
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                    ->modalHeading(__('general.student_registration'))
                    ->form([
                        Select::make('student_id')->native(false)
                            ->label(__('general.select_student'))
                            ->options(fn (): array => Student::query()->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        MonthPicker::make('start_month')
                            ->label(__('general.start_month'))
                            ->default(fn (): string => InstituteSetting::current()->current_month ?: now()->format('Y-m'))
                            ->required(),
                        CheckboxList::make('course_ids')
                            ->label(__('general.courses'))
                            ->options(fn (ProgramType $record): array => Course::query()
                                ->where('program_type_id', $record->id)
                                ->enrollable()
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(function (Course $course): array {
                                    $seats = $course->seats_remaining !== null
                                        ? ' — ('.$course->seats_remaining.' '.__('general.seats_remaining').')'
                                        : '';
                                    return [
                                        $course->id => $course->name.' — '.number_format((float) $course->price).' '.__('general.currency').$seats,
                                    ];
                                })
                                ->all())
                            ->columns(2)
                            ->required()
                            ->default(fn (ProgramType $record): array => Course::query()
                                ->where('program_type_id', $record->id)
                                ->enrollable()
                                ->pluck('id')
                                ->all()),
                        MoneyInput::make('payment_amount')
                            ->label(__('general.initial_payment'))
                            ->minValue(0)
                            ->default(0)
                            ->suffix(__('general.currency')),
                        ...PaymentDetails::fields('payment_method'),
                        DatePicker::make('payment_date')
                            ->label(__('general.date'))
                            ->default(now())
                            ->displayFormat('d/m/Y'),
                    ])
                    ->action(function (ProgramType $record, array $data): void {
                        $data['program_type_id'] = $record->id;
                        $registrations = app(RegistrationService::class)->registerForProgram($data, (int) Auth::id());
                        Notification::make()
                            ->title(__('general.saved'))
                            ->body(count($registrations).' '.__('general.registrations'))
                            ->success()
                            ->send();
                    }),
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
            ProgramTypeResource\RelationManagers\CurriculumRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramTypes::route('/'),
            'create' => Pages\CreateProgramType::route('/create'),
            'edit' => Pages\EditProgramType::route('/{record}/edit'),
        ];
    }
}
