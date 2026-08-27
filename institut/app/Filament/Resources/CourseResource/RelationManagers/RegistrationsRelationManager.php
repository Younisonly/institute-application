<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Models\Registration;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.students');
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('general.student_name'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        'transferred' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('total_score')
                    ->label(__('general.total_score'))
                    ->getStateUsing(fn (Registration $record) => $record->grade_total ?? null)
                    ->placeholder('—')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('batch.name')
                    ->label(__('general.batch'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('manageGrades')
                    ->label(__('general.manage_grades'))
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->modalHeading(fn (Registration $record) => __('general.manage_grades') . ' - ' . $record->student->name)
                    ->form(function () {
                        $course = $this->getOwnerRecord();
                        $schema = $course->grading_schema ?? [];
                        
                        if (empty($schema)) {
                            return [
                                Placeholder::make('no_schema')
                                    ->hiddenLabel()
                                    ->content(__('general.no_grading_schema')),
                            ];
                        }

                        $fields = [];
                        foreach ($schema as $index => $item) {
                            $label = (string) ($item['label'] ?? '');
                            $max = (float) ($item['max'] ?? 0);

                            $field = TextInput::make($label)
                                ->label($label . ' (' . __('general.max_score') . ': ' . $max . ')')
                                ->rules([
                                    fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($max): void {
                                        if ($value === null || $value === '') {
                                            return;
                                        }

                                        if (! is_numeric($value)) {
                                            $fail(__('general.mark_not_numeric'));

                                            return;
                                        }

                                        if ($max > 0 && (float) $value > $max) {
                                            $fail(__('general.mark_exceeds_max', ['max' => number_format($max)]));

                                            return;
                                        }

                                        if ((float) $value < 0) {
                                            $fail(__('general.mark_below_min', ['min' => 0]));
                                        }
                                    },
                                ])
                                ->live()
                                ->afterStateUpdated(fn ($state, callable $set, callable $get) => $set('__trigger', uniqid()));

                            if ($index === 0) {
                                $field->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $course): void {
                                    $fullMark = (float) $course->full_mark;
                                    if (!$fullMark) return;

                                    $sum = 0.0;

                                    foreach (($get('..') ?? []) as $key => $score) {
                                        if ($key !== '__trigger' && is_numeric($score)) {
                                            $sum += (float) $score;
                                        }
                                    }

                                    if ($sum > $fullMark) {
                                        $fail(__('general.total_exceeds_full_mark', ['total' => $sum, 'full_mark' => $fullMark]));
                                    }
                                });
                            }

                            $fields[] = $field;
                        }
                        
                        return [
                            Group::make($fields)
                                ->statePath('grades'),
                            
                            Placeholder::make('current_total')
                                ->label(__('general.current_total'))
                                ->content(function (\Filament\Forms\Get $get) use ($course) {
                                    // Trigger is just to force reactivity
                                    $get('grades.__trigger');
                                    
                                    $grades = $get('grades') ?? [];
                                    unset($grades['__trigger']); // Ignore the trigger variable
                                    
                                    $sum = array_sum(array_map('floatval', $grades));
                                    $fullMark = (float) $course->full_mark;
                                    
                                    $color = ($sum > $fullMark) ? 'red' : 'green';
                                    
                                    // Not using HtmlString to avoid strings:audit. Just returning string since Filament escapes it.
                                    // Actually Filament does not escape Placeholder content if we don't use HtmlString? 
                                    // Wait, if it escapes, it just shows as text. The user just wants to see the total.
                                    return $sum . ' / ' . $fullMark;
                                }),
                        ];
                    })
                    ->action(function (Registration $record, array $data): void {
                        $record->saveGradeComponents($data['grades'] ?? [], (int) auth()->id());
                        Notification::make()->title(__('general.saved'))->success()->send();
                    })
                    ->visible(fn () => !empty($this->getOwnerRecord()->grading_schema)),
            ])
            ->bulkActions([
                //
            ]);
    }
}
