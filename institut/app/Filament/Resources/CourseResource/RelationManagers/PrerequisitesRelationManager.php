<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PrerequisitesRelationManager extends RelationManager
{
    protected static string $relationship = 'prerequisites';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.prerequisites');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('prerequisiteCourse.name')
            ->columns([
                TextColumn::make('prerequisiteCourse.name')
                    ->label(__('general.prerequisite_course'))
                    ->weight('semibold'),
                TextColumn::make('rule_type')
                    ->label(__('general.rule_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.rule_type_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'required' => 'danger',
                        'alt_group' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('group_no')
                    ->label(__('general.group_no'))
                    ->placeholder('—'),
                TextColumn::make('min_mark')
                    ->label(__('general.min_mark'))
                    ->placeholder('—'),
                TextColumn::make('min_attendance_percent')
                    ->label(__('general.min_attendance_percent'))
                    ->suffix('%')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading(__('general.prerequisites_empty'))
            ->emptyStateDescription(__('general.prerequisites_empty_hint'));
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('prerequisite_course_id')
                ->label(__('general.prerequisite_course'))
                ->options(fn (): array => Course::query()
                    ->where('program_type_id', (int) ($this->getOwnerRecord()?->program_type_id ?? 0))
                    ->whereKeyNot((int) ($this->getOwnerRecord()?->id ?? 0))
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->preload()
                ->unique(
                    table: 'course_prerequisites',
                    column: 'prerequisite_course_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, $component) => $rule->where('course_id', (int) ($this->getOwnerRecord()?->id ?? 0)),
                ),
            Select::make('rule_type')
                ->label(__('general.rule_type'))
                ->options([
                    'required' => __('general.rule_type_required'),
                    'alt_group' => __('general.rule_type_alt_group'),
                    'recommended' => __('general.rule_type_recommended'),
                ])
                ->default('required')
                ->required(),
            TextInput::make('group_no')
                ->label(__('general.group_no'))
                ->numeric()
                ->minValue(1)
                ->helperText(__('general.group_no_hint')),
            TextInput::make('min_mark')
                ->label(__('general.min_mark'))
                ->numeric()
                ->minValue(0)
                ->helperText(__('general.min_mark_hint')),
            TextInput::make('min_attendance_percent')
                ->label(__('general.min_attendance_percent'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText(__('general.min_attendance_hint')),
        ]);
    }
}