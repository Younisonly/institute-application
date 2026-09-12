<?php

namespace App\Filament\Resources\StaffAttendanceResource\Pages;

use App\Filament\Resources\StaffAttendanceResource;
use App\Models\StaffAttendance;
use App\Services\AttendanceManagementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class EmployeeAttendanceHistory extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = StaffAttendanceResource::class;

    protected static string $view = 'filament.resources.staff-attendance-resource.pages.employee-attendance-history';

    protected function resolveRecord(int | string $key): \Illuminate\Database\Eloquent\Model
    {
        return \App\Models\Staff::findOrFail($key);
    }

    public function getTitle(): string
    {
        return $this->record->name.' — '.__('general.attendance_history');
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('general.attendance_history');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StaffAttendance::query()->where('staff_id', $this->record->id)->with(['courseBatch', 'createdBy', 'staff']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('general.date'))
                    ->formatStateUsing(fn ($state): string => \Carbon\Carbon::parse($state)->translatedFormat('l d/m/Y'))
                    ->sortable(),
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
                    ->formatStateUsing(function (StaffAttendance $record): string {
                        $actual = number_format((float) $record->hours_worked, 2);
                        if ($record->courseBatch) {
                            $planned = number_format((float) ($record->courseBatch->daily_hours ?? 2.0), 2);
                            return "{$actual} / {$planned} ".__('general.hours');
                        }

                        return "{$actual} ".__('general.hours');
                    })
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label(__('general.created_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label(__('general.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')
                            ->label(__('general.from_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('to_date')
                            ->label(__('general.to_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from_date'], fn ($q) => $q->whereDate('date', '>=', $data['from_date']))
                            ->when($data['to_date'], fn ($q) => $q->whereDate('date', '<=', $data['to_date']));
                    }),
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
                Tables\Actions\Action::make('view_details')
                    ->label(__('general.details'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->slideOver()
                    ->modalHeading(__('general.attendance_details'))
                    ->modalSubmitAction(false)
                    ->infolist([
                        InfoSection::make()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('date')
                                    ->label(__('general.date'))
                                    ->formatStateUsing(fn ($state): string => \Carbon\Carbon::parse($state)->translatedFormat('l d/m/Y')),
                                TextEntry::make('status')
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
                                TextEntry::make('staff.name')
                                    ->label(__('general.staff_member')),
                                TextEntry::make('staff.is_teacher')
                                    ->label(__('general.staff_type'))
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? __('general.teacher') : __('general.employee'))
                                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                                TextEntry::make('courseBatch.name')
                                    ->label(__('general.course_batch'))
                                    ->placeholder('—'),
                                TextEntry::make('primary_teacher')
                                    ->label(__('general.primary_teacher'))
                                    ->state(function (StaffAttendance $record): string {
                                        if (! $record->course_batch_id) {
                                            return '—';
                                        }
                                        $ts = \App\Models\TeachingSession::query()
                                            ->where('course_batch_id', $record->course_batch_id)
                                            ->whereDate('date', $record->date->toDateString())
                                            ->first();

                                        return $ts?->primaryTeacher?->name ?? $record->courseBatch?->teacher?->name ?? '—';
                                    }),
                                TextEntry::make('actual_teacher')
                                    ->label(__('general.actual_teacher'))
                                    ->state(function (StaffAttendance $record): string {
                                        if (! $record->course_batch_id) {
                                            return '—';
                                        }
                                        $ts = \App\Models\TeachingSession::query()
                                            ->where('course_batch_id', $record->course_batch_id)
                                            ->whereDate('date', $record->date->toDateString())
                                            ->first();

                                        return $ts?->actualTeacher?->name ?? $record->staff?->name ?? '—';
                                    })
                                    ->helperText(function (StaffAttendance $record): ?string {
                                        if (! $record->course_batch_id) {
                                            return null;
                                        }
                                        $ts = \App\Models\TeachingSession::query()
                                            ->where('course_batch_id', $record->course_batch_id)
                                            ->whereDate('date', $record->date->toDateString())
                                            ->first();

                                        if ($ts && $ts->primary_teacher_id && $ts->actual_teacher_id && (int) $ts->primary_teacher_id !== (int) $ts->actual_teacher_id) {
                                            $primaryName = $ts->primaryTeacher?->name ?? '—';

                                            return __('general.substitute_teacher_notice', ['primary' => $primaryName]);
                                        }

                                        return null;
                                    }),
                                TextEntry::make('planned_hours')
                                    ->label(__('general.planned_hours'))
                                    ->state(function (StaffAttendance $record): string {
                                        if (! $record->course_batch_id) {
                                            return '—';
                                        }
                                        $ts = \App\Models\TeachingSession::query()
                                            ->where('course_batch_id', $record->course_batch_id)
                                            ->whereDate('date', $record->date->toDateString())
                                            ->first();

                                        $planned = $ts?->planned_hours ?? $record->courseBatch?->daily_hours ?? 2.0;

                                        return number_format((float) $planned, 2).' '.__('general.hour');
                                    }),
                                TextEntry::make('hours_worked')
                                    ->label(__('general.hours_worked'))
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' '.__('general.hour')),
                                TextEntry::make('cancellation_reason')
                                    ->label(__('general.cancellation_reason'))
                                    ->state(function (StaffAttendance $record): ?string {
                                        $ts = \App\Models\TeachingSession::query()
                                            ->where('course_batch_id', $record->course_batch_id)
                                            ->whereDate('date', $record->date->toDateString())
                                            ->first();

                                        return $ts?->cancellation_reason;
                                    })
                                    ->placeholder('—')
                                    ->visible(fn (StaffAttendance $record): bool => $record->status === 'cancelled_session'),
                                TextEntry::make('createdBy.name')
                                    ->label(__('general.created_by'))
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label(__('general.created_at'))
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('notes')
                                    ->label(__('general.notes'))
                                    ->columnSpanFull()
                                    ->placeholder('—'),
                            ]),
                    ]),
                Tables\Actions\Action::make('edit_attendance')
                    ->label(__('general.edit'))
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->slideOver()
                    ->visible(fn (?StaffAttendance $record): bool => $record !== null && ($record->date->gte(now()->startOfDay()) || (auth()->user() && auth()->user()->hasAnyRole(['admin', 'accountant', 'registrar']))))
                    ->modalHeading(fn (?StaffAttendance $record): string => $record ? __('general.edit_attendance').' — '.\Carbon\Carbon::parse($record->date)->translatedFormat('l d/m/Y') : __('general.edit_attendance'))
                    ->modalDescription(function (?StaffAttendance $record): ?string {
                        if ($record && $record->date->lt(now()->startOfDay())) {
                            return __('general.past_date_warning_dialog_desc');
                        }

                        return null;
                    })
                    ->modalSubmitActionLabel(__('general.save'))
                    ->form(fn (?StaffAttendance $record): array => StaffAttendanceResource::getAttendanceFormSchema($record?->staff))
                    ->fillForm(function (StaffAttendance $record): array {
                        $teachingSession = \App\Models\TeachingSession::query()
                            ->where('course_batch_id', $record->course_batch_id)
                            ->whereDate('date', $record->date->toDateString())
                            ->first();

                        return [
                            'id' => $record->id,
                            'staff_id' => $record->staff_id,
                            'date' => $record->date->toDateString(),
                            'status' => $record->status,
                            'course_batch_id' => $record->course_batch_id,
                            'period_id' => $teachingSession?->period_id ?? $record->courseBatch?->periods()->first()?->id,
                            'primary_teacher_id' => $teachingSession?->primary_teacher_id ?? $record->courseBatch?->teacher_id ?? $record->staff_id,
                            'actual_teacher_id' => $teachingSession?->actual_teacher_id ?? $record->staff_id,
                            'planned_hours' => $teachingSession?->planned_hours ?? $record->courseBatch?->daily_hours ?? 2.00,
                            'hours_worked' => $record->hours_worked,
                            'cancellation_reason' => $teachingSession?->cancellation_reason,
                            'notes' => $record->notes,
                        ];
                    })
                    ->action(function (StaffAttendance $record, array $data): void {
                        try {
                            $data['id'] = $record->id;
                            $data['staff_id'] = $record->staff_id;
                            $data['date'] = $data['date'] ?? $record->date->toDateString();
                            $att = app(AttendanceManagementService::class)->saveAttendance($data);

                            if ($att->getAttribute('is_closed_payroll_edit')) {
                                Notification::make()
                                    ->title(__('general.attendance_saved_retroactive_notice'))
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('general.saved'))
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('delete_attendance')
                    ->label(__('general.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (?StaffAttendance $record): bool => $record !== null && ($record->date->gte(now()->startOfDay()) || (auth()->user() && auth()->user()->hasAnyRole(['admin', 'accountant', 'registrar']))))
                    ->action(function (StaffAttendance $record): void {
                        try {
                            app(AttendanceManagementService::class)->deleteAttendance($record);
                            Notification::make()
                                ->title(__('general.saved'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public function getStatsProperty(): array
    {
        $query = StaffAttendance::query()->where('staff_id', $this->record->id);

        $filterData = $this->getTableFilterState('date_range') ?? [];
        $fromDate = $filterData['from_date'] ?? null;
        $toDate = $filterData['to_date'] ?? null;

        if ($fromDate) {
            $query->whereDate('date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('date', '<=', $toDate);
        }

        $totalRecorded = (clone $query)->count();
        $presentDays = (clone $query)->whereIn('status', ['present', 'late'])->count();
        $absentDays = (clone $query)->where('status', 'absent')->count();
        $totalHours = (float) (clone $query)->sum('hours_worked');
        $rate = $totalRecorded > 0 ? round(($presentDays / $totalRecorded) * 100, 1) : 0.0;

        return [
            'present' => $presentDays,
            'absent' => $absentDays,
            'hours' => $totalHours,
            'rate' => $rate,
        ];
    }
}
