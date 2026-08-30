<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use App\Filament\Resources\StaffAttendanceResource\Pages;
use App\Models\CourseBatch;
use App\Models\Staff;
use App\Models\StaffAttendance;
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

    protected static ?string $model = StaffAttendance::class;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('staff_id')
                            ->label(__('general.staff_member'))
                            ->options(Staff::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('course_batch_id')
                            ->label(__('general.course_batch'))
                            ->options(CourseBatch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if ($state) {
                                    $batch = CourseBatch::find($state);
                                    if ($batch && $batch->daily_hours > 0) {
                                        $set('hours_worked', $batch->daily_hours);
                                    }
                                }
                            }),
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
                        TextInput::make('hours_worked')
                            ->label(__('general.hours_worked'))
                            ->numeric()
                            ->default(2.00)
                            ->required(),
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
                TextColumn::make('staff.name')
                    ->label(__('general.staff_member'))
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('courseBatch.name')
                    ->label(__('general.course_batch'))
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'excused' => 'info',
                        'absent' => 'danger',
                        'cancelled_session' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('hours_worked')
                    ->label(__('general.hours_worked'))
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('notes')
                    ->label(__('general.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('staff_id')
                    ->label(__('general.staff_member'))
                    ->options(Staff::query()->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'present' => __('general.present'),
                        'absent' => __('general.absent'),
                        'late' => __('general.late'),
                        'excused' => __('general.excused'),
                        'cancelled_session' => __('general.cancelled_session'),
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
            'index' => Pages\ListStaffAttendances::route('/'),
            'create' => Pages\CreateStaffAttendance::route('/create'),
            'edit' => Pages\EditStaffAttendance::route('/{record}/edit'),
        ];
    }
}
