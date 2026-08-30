<?php

namespace App\Filament\Resources\CourseBatchResource\RelationManagers;

use App\Models\Staff;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeacherAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teacherAssignments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('general.teacher_assignments_history');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('staff_id')
                    ->label(__('general.teacher'))
                    ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('role')
                    ->label(__('general.role'))
                    ->options([
                        'primary' => __('general.role_primary'),
                        'co_teacher' => __('general.role_co_teacher'),
                        'assistant' => __('general.role_assistant'),
                        'substitute' => __('general.role_substitute'),
                    ])
                    ->default('primary')
                    ->required(),
                DatePicker::make('start_date')
                    ->label(__('general.start_date'))
                    ->default(now())
                    ->required(),
                DatePicker::make('end_date')
                    ->label(__('general.end_date'))
                    ->nullable(),
                Toggle::make('is_active')
                    ->label(__('general.is_active'))
                    ->default(true),
                Textarea::make('notes')
                    ->label(__('general.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label(__('general.teacher'))
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('role')
                    ->label(__('general.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.role_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'primary' => 'success',
                        'co_teacher' => 'info',
                        'assistant' => 'warning',
                        'substitute' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('start_date')
                    ->label(__('general.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('general.end_date'))
                    ->date('d/m/Y')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('general.is_active'))
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
