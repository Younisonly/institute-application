<?php

namespace App\Filament\Resources\ProgramTypeResource\RelationManagers;

use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CurriculumRelationManager extends RelationManager
{
    protected static string $relationship = 'curriculum';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.curriculum');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('course.name')
            ->columns([
                TextColumn::make('level_no')
                    ->label(__('general.level_no'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('course.name')
                    ->label(__('general.course'))
                    ->weight('semibold'),
                TextColumn::make('semester_no')
                    ->label(__('general.semester_no'))
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->label(__('general.sort_order'))
                    ->placeholder('—'),
                IconColumn::make('is_required')
                    ->label(__('general.is_required'))
                    ->boolean(),
                TextColumn::make('credit_hours')
                    ->label(__('general.credit_hours'))
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

                        $data['level_no'] = max(1, (int) ($data['level_no'] ?? 1));

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading(__('general.curriculum_empty'))
            ->emptyStateDescription(__('general.curriculum_empty_hint'));
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('course_id')
                ->label(__('general.course'))
                ->options(fn (): array => Course::query()
                    ->where('program_type_id', (int) ($this->getOwnerRecord()?->id ?? 0))
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->preload()
                ->unique(
                    table: 'program_course',
                    column: 'course_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, $component) => $rule->where('program_id', $this->getOwnerRecord()?->id ?? 0),
                ),
            TextInput::make('level_no')
                ->label(__('general.level_no'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            TextInput::make('semester_no')
                ->label(__('general.semester_no'))
                ->numeric()
                ->minValue(1),
            TextInput::make('sort_order')
                ->label(__('general.sort_order'))
                ->numeric()
                ->minValue(0)
                ->default(0),
            Toggle::make('is_required')
                ->label(__('general.is_required'))
                ->default(true),
            TextInput::make('credit_hours')
                ->label(__('general.credit_hours'))
                ->numeric()
                ->minValue(0)
                ->step(0.5),
        ]);
    }
}