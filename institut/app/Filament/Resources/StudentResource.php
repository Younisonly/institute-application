<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGuardedDeletes;
use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers\AcademicHistoryRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\EnrollmentTransfersRelationManager;
use App\Filament\Resources\StudentResource\RelationManagers\TransactionsRelationManager;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use RuntimeException;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    use HasGuardedDeletes;
    use HasRbac;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withBalance();
    }

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
        return ['admin', 'registrar'];
    }

    protected static ?string $model = Student::class;
    
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'student_code', 'national_id'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.students');
    }

    public static function getModelLabel(): string
    {
        return __('general.student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.students');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(4)
                    ->schema([
                        Section::make(__('general.profile'))
                            ->columnSpan(3)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')->label(__('general.full_name'))->required()->maxLength(255),
                                TextInput::make('student_code')
                                    ->label(__('general.student_code'))
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('gender')->native(false)
                                    ->label(__('general.gender'))
                                    ->options([
                                        'male' => __('general.male'),
                                        'female' => __('general.female'),
                                    ])
                                    ->default('male'),
                                DatePicker::make('birth_date')->label(__('general.birth_date'))->displayFormat('d/m/Y'),
                                TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(20),
                                TextInput::make('whatsapp_phone')->label(__('general.whatsapp_phone'))->tel()->maxLength(20),
                                TextInput::make('national_id')->label(__('general.national_id'))->maxLength(20),
                                TextInput::make('address')->label(__('general.address'))->maxLength(255),
                                Select::make('education_level')->native(false)
                                    ->label(__('general.education_level'))
                                    ->options([
                                        'basic' => __('general.education_basic'),
                                        'secondary' => __('general.education_secondary'),
                                        'diploma' => __('general.education_diploma'),
                                        'university' => __('general.education_university'),
                                        'other' => __('general.education_other'),
                                    ]),
                                DatePicker::make('join_date')->label(__('general.join_date'))->default(now())->displayFormat('d/m/Y'),
                                Select::make('status')->native(false)
                                    ->label(__('general.status'))
                                    ->options([
                                        'active' => __('general.active'),
                                        'suspended' => __('general.suspended'),
                                        'closed' => __('general.closed'),
                                        'graduate' => __('general.graduate'),
                                    ])
                                    ->default('active')
                                    ->required(),
                            ]),
                        Section::make(__('general.photo'))
                            ->columnSpan(1)
                            ->schema([
                                FileUpload::make('photo_path')
                                    ->label(__('general.photo'))
                                    ->image()
                                    ->avatar()
                                    ->directory('students')
                                    ->imageEditor()
                                    ->circleCropper(),
                            ]),
                    ]),
                Section::make(__('general.guardian'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('guardian_name')->label(__('general.guardian_name'))->maxLength(255),
                        Select::make('guardian_relation')->native(false)
                            ->label(__('general.guardian_relation'))
                            ->options([
                                'father' => __('general.relation_father'),
                                'mother' => __('general.relation_mother'),
                                'brother' => __('general.relation_brother'),
                                'sister' => __('general.relation_sister'),
                                'relative' => __('general.relation_relative'),
                                'other' => __('general.relation_other'),
                            ]),
                        TextInput::make('guardian_phone')->label(__('general.guardian_phone'))->tel()->maxLength(20),
                    ]),
                Section::make(__('general.emergency_contact'))
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('emergency_contact_name')->label(__('general.emergency_contact_name'))->maxLength(255),
                        Select::make('emergency_contact_relation')->native(false)
                            ->label(__('general.emergency_contact_relation'))
                            ->options([
                                'father' => __('general.relation_father'),
                                'mother' => __('general.relation_mother'),
                                'brother' => __('general.relation_brother'),
                                'sister' => __('general.relation_sister'),
                                'relative' => __('general.relation_relative'),
                                'other' => __('general.relation_other'),
                            ]),
                        TextInput::make('emergency_contact_phone')->label(__('general.emergency_contact_phone'))->tel()->maxLength(20),
                    ]),
                Textarea::make('notes')->label(__('general.notes'))->columnSpanFull(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Student::query()->withBalance()->withCount([
                'registrations as active_registrations_count' => fn ($query) => $query->where('status', 'active'),
            ]))
            ->columns([
                ImageColumn::make('photo_path')->label(__('general.photo'))->circular(),
                TextColumn::make('student_code')
                    ->label(__('general.student_code'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('gender')
                    ->label(__('general.gender'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __("general.{$state}") : '')
                    ->toggleable(),
                TextColumn::make('phone')->label(__('general.phone'))->searchable(),
                TextColumn::make('guardian_name')->label(__('general.guardian_name'))->searchable()->toggleable(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? __("general.{$state}") : '')
                    ->color(fn (?string $state): string => match ($state ?? '') {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('balance')
                    ->label(__('general.student_balance'))
                    ->formatStateUsing(fn (?float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($state ?? 0)))
                    ->color(fn (?float $state): string => ((float) ($state ?? 0)) > 0 ? 'danger' : (((float) ($state ?? 0)) < 0 ? 'success' : 'gray'))
                    ->weight('bold')
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (?float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) ($state ?? 0), true))
                    ),
                TextColumn::make('active_registrations_count')
                    ->label(__('general.active_courses'))
                    ->formatStateUsing(fn (?int $state): string => trans_choice('general.active_courses_count', (int) ($state ?? 0), ['count' => (int) ($state ?? 0)]))
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('join_date')->label(__('general.join_date'))->date('d/m/Y')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->native(false)
                    ->label(__('general.status'))
                    ->options([
                        'active' => __('general.active'),
                        'suspended' => __('general.suspended'),
                        'closed' => __('general.closed'),
                        'graduate' => __('general.graduate'),
                    ]),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('delete')
                    ->label(__('general.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Student $record): void {
                        try {
                            $record->delete();
                            Notification::make()
                                ->title(__('general.deleted'))
                                ->success()
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\RestoreAction::make()->label(__('general.restore')),
                static::guardedForceDeleteAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('deleteStudents')
                        ->label(__('general.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $deleted = 0;
                            $skipped = 0;
                            $reason = null;

                            foreach ($records as $record) {
                                try {
                                    $record->delete();
                                    $deleted++;
                                } catch (RuntimeException $e) {
                                    $skipped++;
                                    $reason = $reason ?? $e->getMessage();
                                }
                            }

                            Notification::make()
                                ->title(__('general.bulk_delete_result', ['deleted' => $deleted, 'skipped' => $skipped]))
                                ->body($skipped > 0 ? $reason : null)
                                ->color($skipped > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        }),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    static::guardedForceDeleteBulkAction(),
                ]),
            ]);

    }

    public static function getRelations(): array
    {
        return [
            AcademicHistoryRelationManager::class,
            TransactionsRelationManager::class,
            EnrollmentTransfersRelationManager::class,
            CertificatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
