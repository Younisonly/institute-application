<?php

namespace App\Filament\Resources\TeachingSessionResource\Pages;

use App\Filament\Resources\TeachingSessionResource;
use App\Models\Staff;
use App\Models\TeachingSession;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewTeachingSessions extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TeachingSessionResource::class;

    protected static string $view = 'filament.resources.teaching-sessions.pages.view-teaching-sessions';

    public function getTitle(): string
    {
        return $this->record->name . ' — ' . __('general.sessions_report');
    }

    public function getSubheading(): ?string
    {
        return __('general.course') . ': ' . ($this->record->course?->name ?? '—')
            . ' | ' . __('general.primary_teacher') . ': ' . ($this->record->teacher?->name ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('record_session')
                ->label(__('general.add_session'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    DatePicker::make('date')
                        ->label(__('general.date'))
                        ->default(now())
                        ->required(),
                    Select::make('period_id')
                        ->label(__('general.period'))
                        ->options(fn (): array => $this->record->periods()
                            ->get(['id', 'name_ar', 'name_en'])
                            ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                            ->all())
                        ->default(fn () => $this->record->periods()->first()?->id)
                        ->searchable(),
                    Select::make('actual_teacher_id')
                        ->label(__('general.actual_teacher'))
                        ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                        ->default($this->record->teacher_id)
                        ->required()
                        ->searchable(),
                    Select::make('status')
                        ->label(__('general.status'))
                        ->options([
                            'completed'   => __('general.status_completed'),
                            'substituted' => __('general.status_substituted'),
                            'cancelled'   => __('general.status_cancelled'),
                            'postponed'   => __('general.status_postponed'),
                        ])
                        ->default('completed')
                        ->required()
                        ->live(),
                    TextInput::make('actual_hours')
                        ->label(__('general.actual_hours'))
                        ->numeric()
                        ->default($this->record->daily_hours ?? 2.0)
                        ->step(0.5)
                        ->required(),
                    TextInput::make('cancellation_reason')
                        ->label(__('general.cancellation_reason'))
                        ->visible(fn (\Filament\Forms\Get $get) => in_array($get('status'), ['cancelled', 'postponed']))
                        ->required(fn (\Filament\Forms\Get $get) => $get('status') === 'cancelled'),
                    Textarea::make('notes')
                        ->label(__('general.notes'))
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    TeachingSession::create([
                        'course_batch_id'    => $this->record->id,
                        'date'               => $data['date'],
                        'period_id'          => $data['period_id'] ?? $this->record->periods()->first()?->id,
                        'primary_teacher_id' => $this->record->teacher_id,
                        'actual_teacher_id'  => $data['actual_teacher_id'],
                        'status'             => $data['status'],
                        'planned_hours'      => $this->record->daily_hours ?? 2.0,
                        'actual_hours'       => (float) $data['actual_hours'],
                        'cancellation_reason' => $data['cancellation_reason'] ?? null,
                        'notes'              => $data['notes'] ?? null,
                        'created_by'         => auth()->id(),
                    ]);

                    Notification::make()
                        ->title(__('general.session_created'))
                        ->success()
                        ->send();

                    $this->redirect(
                        \App\Filament\Pages\BatchAttendance::getUrl() . '?batch=' . $this->record->id
                    );
                }),

            Action::make('print')
                ->label(__('general.print_report'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(route('reports.teaching-sessions.batch.print', ['batch' => $this->record]))
                ->openUrlInNewTab(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TeachingSession::query()
                    ->where('course_batch_id', $this->record->id)
                    ->with(['primaryTeacher', 'actualTeacher', 'createdBy', 'period'])
            )
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('general.date'))
                    ->formatStateUsing(fn ($state): string => \Carbon\Carbon::parse($state)->translatedFormat('l d/m/Y'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('period.name')
                    ->label(__('general.period'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('actualTeacher.name')
                    ->label(__('general.actual_teacher'))
                    ->searchable()
                    ->placeholder('—')
                    ->description(function (TeachingSession $record): ?string {
                        if (
                            $record->primary_teacher_id
                            && $record->actual_teacher_id
                            && (int) $record->primary_teacher_id !== (int) $record->actual_teacher_id
                        ) {
                            return __('general.status_substituted') . ' (' . __('general.primary_teacher') . ': ' . ($record->primaryTeacher?->name ?? '—') . ')';
                        }

                        return null;
                    }),

                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'completed'   => 'success',
                        'substituted' => 'info',
                        'cancelled'   => 'danger',
                        'postponed'   => 'warning',
                        default       => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed'   => 'heroicon-m-check-circle',
                        'substituted' => 'heroicon-m-arrows-right-left',
                        'cancelled'   => 'heroicon-m-x-circle',
                        'postponed'   => 'heroicon-m-clock',
                        default       => 'heroicon-m-question-mark-circle',
                    }),

                TextColumn::make('actual_hours')
                    ->label(__('general.actual_hours'))
                    ->formatStateUsing(function (TeachingSession $record): string {
                        $actual  = (float) $record->actual_hours;
                        $planned = (float) $record->planned_hours;
                        $str     = number_format($actual, 1) . ' / ' . number_format($planned, 1) . ' ' . __('general.hours_short');

                        if ($actual > 0 && $planned > 0 && $actual < $planned) {
                            $str .= ' ⬇';
                        }

                        return $str;
                    })
                    ->color(function (TeachingSession $record): string {
                        $actual  = (float) $record->actual_hours;
                        $planned = (float) $record->planned_hours;

                        if ($actual < $planned && $actual > 0) {
                            return 'warning';
                        }

                        return 'gray';
                    })
                    ->summarize(
                        Sum::make('actual_hours')
                            ->label(__('general.total'))
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 1) . ' ' . __('general.hours_short'))
                    ),

                TextColumn::make('cancellation_reason')
                    ->label(__('general.cancellation_reason'))
                    ->placeholder('—')
                    ->badge()
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->teachingSessions()->whereIn('status', ['cancelled', 'postponed'])->exists()),

                TextColumn::make('notes')
                    ->label(__('general.notes'))
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('createdBy.name')
                    ->label(__('general.author'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('general.status'))
                    ->options([
                        'completed'   => __('general.status_completed'),
                        'substituted' => __('general.status_substituted'),
                        'cancelled'   => __('general.status_cancelled'),
                        'postponed'   => __('general.status_postponed'),
                    ]),

                Tables\Filters\SelectFilter::make('actual_teacher_id')
                    ->label(__('general.actual_teacher'))
                    ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id')),

                Tables\Filters\Filter::make('substituted_only')
                    ->label(__('general.status_substituted'))
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->where('status', 'substituted')
                          ->orWhereColumn('primary_teacher_id', '!=', 'actual_teacher_id');
                    }))
                    ->toggle(),

                Tables\Filters\Filter::make('short_sessions')
                    ->label(__('general.actual_hours') . ' < ' . __('general.planned_hours'))
                    ->query(fn (Builder $query): Builder => $query->whereColumn('actual_hours', '<', 'planned_hours')->where('actual_hours', '>', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')->label(__('general.start_date')),
                        DatePicker::make('to_date')->label(__('general.end_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        InfoSection::make()
                            ->schema([
                                TextEntry::make('date')
                                    ->label(__('general.date'))
                                    ->formatStateUsing(fn ($state): string => \Carbon\Carbon::parse($state)->translatedFormat('l d/m/Y')),
                                TextEntry::make('period.name')
                                    ->label(__('general.period'))
                                    ->placeholder('—'),
                                TextEntry::make('primaryTeacher.name')
                                    ->label(__('general.primary_teacher'))
                                    ->placeholder('—'),
                                TextEntry::make('actualTeacher.name')
                                    ->label(__('general.actual_teacher'))
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label(__('general.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => __("general.status_{$state}"))
                                    ->color(fn (string $state): string => match ($state) {
                                        'completed'   => 'success',
                                        'substituted' => 'info',
                                        'cancelled'   => 'danger',
                                        'postponed'   => 'warning',
                                        default       => 'gray',
                                    }),
                                TextEntry::make('planned_hours')
                                    ->label(__('general.planned_hours'))
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . ' ' . __('general.hours_short')),
                                TextEntry::make('actual_hours')
                                    ->label(__('general.actual_hours'))
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . ' ' . __('general.hours_short')),
                                TextEntry::make('cancellation_reason')
                                    ->label(__('general.cancellation_reason'))
                                    ->placeholder('—'),
                                TextEntry::make('notes')
                                    ->label(__('general.notes'))
                                    ->placeholder('—'),
                                TextEntry::make('createdBy.name')
                                    ->label(__('general.author'))
                                    ->placeholder('—'),
                            ]),
                    ]),

                Tables\Actions\EditAction::make()
                    ->form([
                        DatePicker::make('date')
                            ->label(__('general.date'))
                            ->required(),
                        Select::make('period_id')
                            ->label(__('general.period'))
                            ->options(fn (): array => $this->record->periods()
                                ->get(['id', 'name_ar', 'name_en'])
                                ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                                ->all())
                            ->searchable(),
                        Select::make('actual_teacher_id')
                            ->label(__('general.actual_teacher'))
                            ->options(Staff::query()->where('is_teacher', true)->pluck('name', 'id'))
                            ->required(),
                        Select::make('status')
                            ->label(__('general.status'))
                            ->options([
                                'completed'   => __('general.status_completed'),
                                'substituted' => __('general.status_substituted'),
                                'cancelled'   => __('general.status_cancelled'),
                                'postponed'   => __('general.status_postponed'),
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('actual_hours')
                            ->label(__('general.actual_hours'))
                            ->numeric()
                            ->step(0.5)
                            ->required(),
                        TextInput::make('cancellation_reason')
                            ->label(__('general.cancellation_reason'))
                            ->visible(fn (\Filament\Forms\Get $get) => in_array($get('status'), ['cancelled', 'postponed'])),
                        Textarea::make('notes')
                            ->label(__('general.notes'))
                            ->rows(2),
                    ])
                    ->successNotificationTitle(__('general.session_updated')),

                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle(__('general.session_deleted')),
            ])
            ->bulkActions([]);
    }
}
