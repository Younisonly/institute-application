<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGuardedDeletes;
use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\StaffResource\Pages;
use App\Filament\Resources\StaffResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\StaffResource\RelationManagers\TransactionsRelationManager;
use App\Models\Course;
use App\Models\JobTitle;
use App\Models\Staff;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    use HasGuardedDeletes;
    use HasRbac;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withAccount();
    }

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static function createRoles(): array
    {
        return ['admin'];
    }

    protected static function editRoles(): array
    {
        return ['admin'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Staff::class;
    
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_staff');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.staff');
    }

    public static function getModelLabel(): string
    {
        return __('general.staff_member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.staff');
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
                                TextInput::make('name')->label(__('general.name'))->required()->maxLength(255),
                                Select::make('job_title_id')->native(false)
                                    ->label(__('general.job_title'))
                                    ->relationship('jobTitle', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label(__('general.name'))
                                            ->required()
                                            ->unique(JobTitle::class, 'name'),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        return JobTitle::create($data)->getKey();
                                    }),
                                TextInput::make('phone')->label(__('general.phone'))->tel()->maxLength(20),
                                Select::make('salary_type')->native(false)
                                    ->label(__('general.salary_type'))
                                    ->options([
                                        'monthly' => __('general.monthly'),
                                        'percentage' => __('general.percentage'),
                                        'per_hour' => __('general.per_hour'),
                                    ])
                                    ->default('monthly')
                                    ->required()
                                    ->live(),
                                MoneyInput::make('salary_value')
                                    ->label(fn (Get $get): string => $get('salary_type') === 'per_hour'
                                        ? __('general.hourly_rate')
                                        : __('general.salary_value'))
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => $get('salary_type') !== 'percentage')
                                    ->required(fn (Get $get): bool => $get('salary_type') !== 'percentage'),
                                TextInput::make('percentage_value')
                                    ->label(__('general.percentage_value'))
                                    ->numeric()->maxValue(999999999999)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->visible(fn (Get $get): bool => $get('salary_type') === 'percentage')
                                    ->required(fn (Get $get): bool => $get('salary_type') === 'percentage'),
                                TextInput::make('contract_no')->label(__('general.contract_no'))->maxLength(50),
                                Select::make('status')->native(false)
                                    ->label(__('general.status'))
                                    ->options([
                                        'active' => __('general.active'),
                                        'inactive' => __('general.inactive'),
                                    ])
                                    ->default('active')
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_teacher')
                                    ->label(__('general.is_teacher'))
                                    ->default(false)
                                    ->live(),
                            ]),
                        Section::make(__('general.photo'))
                            ->columnSpan(1)
                            ->schema([
                                FileUpload::make('photo_path')
                                    ->label(__('general.photo'))
                                    ->image()
                                    ->avatar()
                                    ->directory('staff')
                                    ->imageEditor()
                                    ->circleCropper(),
                            ]),
                    ]),
                Textarea::make('notes')->label(__('general.notes'))->columnSpanFull(),
                Section::make(__('general.teacher_specialties'))
                    ->collapsed()
                    ->visible(fn (Get $get): bool => (bool) $get('is_teacher'))
                    ->schema([
                        Select::make('courses')->native(false)
                            ->label(__('general.courses'))
                            ->multiple()
                            ->relationship('courses', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('general.teacher_specialties_hint')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Staff::query()->withAccount())
            ->columns([
                ImageColumn::make('photo_path')->label(__('general.photo'))->circular(),
                TextColumn::make('name')->label(__('general.name'))->searchable()->weight('semibold'),
                TextColumn::make('phone')->label(__('general.phone'))->searchable(),
                TextColumn::make('jobTitle.name')
                    ->label(__('general.job_title'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('salary_type')
                    ->label(__('general.salary_type'))
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}")),
                TextColumn::make('salary_value')
                    ->label(__('general.salary_value'))
                    ->formatStateUsing(fn (string $state, Staff $record): string => $record->salary_type === 'percentage'
                        ? ($record->percentage_value !== null ? number_format((float) $record->percentage_value, 0) . '%' : '—')
                        : number_format((float) $state) . ' ' . __('general.currency'))
                    ->toggleable(),
                TextColumn::make('outstanding_advance')
                    ->label(__('general.outstanding_advance'))
                    ->formatStateUsing(fn (float $state): string => number_format($state) . ' ' . __('general.currency'))
                    ->color(fn (float $state): string => $state > 0 ? 'warning' : 'success')
                    ->weight('bold'),
                TextColumn::make('total_salary_paid')
                    ->label(__('general.salary_paid'))
                    ->formatStateUsing(fn (float $state): string => number_format($state) . ' ' . __('general.currency'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                \Filament\Tables\Columns\IconColumn::make('is_teacher')
                    ->label(__('general.is_teacher'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('salary_type')->native(false)
                    ->label(__('general.salary_type'))
                    ->options([
                        'monthly' => __('general.monthly'),
                        'percentage' => __('general.percentage'),
                        'per_hour' => __('general.per_hour'),
                    ]),
                Tables\Filters\SelectFilter::make('job_title_id')->native(false)
                    ->label(__('general.job_title'))
                    ->relationship('jobTitle', 'name'),
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make()->label(__('general.restore')),
                static::guardedForceDeleteAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    static::guardedForceDeleteBulkAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            TransactionsRelationManager::class,
            StaffResource\RelationManagers\TeacherAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'view' => Pages\ViewStaff::route('/{record}'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
