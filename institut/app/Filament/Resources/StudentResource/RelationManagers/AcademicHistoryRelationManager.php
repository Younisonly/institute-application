<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Filament\Resources\RegistrationResource;
use App\Models\Registration;
use Carbon\CarbonImmutable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcademicHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('general.academic_history');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('course.name')
            ->defaultSort('start_month', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'course',
                'batch.periods',
                'attendanceRecords.session',
            ]))
            ->columns([
                TextColumn::make('course.name')
                    ->label(__('general.course'))
                    ->weight('bold')
                    ->description(fn (?Registration $record): string => $record?->batch !== null
                        ? ($record->batch->name.($record->batch->year ? ' ('.$record->batch->year.')' : ''))
                        : '—'),
                TextColumn::make('period')
                    ->label(__('general.period'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->getStateUsing(fn (?Registration $record): ?string => $record?->batch?->periods_label),
                TextColumn::make('start_month')
                    ->label(__('general.duration'))
                    ->formatStateUsing(fn (?Registration $record): string => $this->durationFor($record))
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'completed' => 'info',
                        'pending' => 'gray',
                        'closed' => 'gray',
                        'withdrawn', 'cancelled' => 'danger',
                        'transferred' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('result')
                    ->label(__('general.result'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('general.result_'.($state ?? 'pending')))
                    ->color(fn (?string $state): string => match ($state ?? 'pending') {
                        'pass' => 'success',
                        'fail' => 'danger',
                        'incomplete' => 'warning',
                        'absent', 'pending' => 'gray',
                        'withdrawn' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (?Registration $record): ?string => $record?->result_finalized_at !== null
                        ? __('general.result_finalized', ['date' => $record->result_finalized_at->format('d/m/Y')])
                        : null),
                TextColumn::make('grade_total')
                    ->label(__('general.final_mark'))
                    ->formatStateUsing(fn (?Registration $record): string => $this->markFor($record))
                    ->description(fn (?Registration $record): ?string => $this->gradeLabelFor($record))
                    ->color(fn (?Registration $record): string => $this->markColorFor($record))
                    ->weight('bold'),
                TextColumn::make('attendance_rate')
                    ->label(__('general.attendance_rate'))
                    ->badge()
                    ->formatStateUsing(fn (?Registration $record): ?string => $this->attendanceRateFor($record))
                    ->description(fn (?Registration $record): ?string => $this->attendanceNoteFor($record))
                    ->color(fn (?Registration $record): string => $this->attendanceColorFor($record)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (?Registration $record): string => $record !== null
                        ? RegistrationResource::getUrl('view', ['record' => $record])
                        : '#'),
            ])
            ->emptyStateHeading(__('general.no_enrollments'))
            ->emptyStateDescription(__('general.no_enrollments_hint'));
    }

    private function durationFor(?Registration $record): string
    {
        if ($record === null || $record->start_month === null) {
            return '—';
        }

        $start = $record->start_month;

        $months = (int) ($record->months_count ?? $record->course?->months ?? 0);

        if ($months <= 0) {
            return $start;
        }

        $end = CarbonImmutable::createFromFormat('Y-m', $start)
            ->addMonths($months - 1)
            ->format('Y-m');

        return $start.' → '.$end;
    }

    private function markFor(?Registration $record): string
    {
        if ($record === null || $record->grade_total === null) {
            return '—';
        }

        $full = (float) ($record->course?->full_mark ?? 0);
        $total = (float) $record->grade_total;

        $percent = $full > 0 ? round($total / $full * 100) : null;

        return $percent !== null ? $percent.'%' : $total.'/'.($full > 0 ? $full : '—');
    }

    private function gradeLabelFor(?Registration $record): ?string
    {
        if ($record === null || $record->grade_total === null) {
            return null;
        }

        $grades = is_array($record->grades) ? $record->grades : [];

        if (! empty($grades['grade'])) {
            return (string) $grades['grade'];
        }

        $course = $record->course;

        if ($course !== null) {
            $schemaGrade = $course->gradeFor((float) $record->grade_total);

            if ($schemaGrade !== null) {
                return $schemaGrade;
            }
        }

        $full = (float) ($course?->full_mark ?? 0);

        if ($full <= 0) {
            return null;
        }

        return $this->standardGradeLabel(round((float) $record->grade_total / $full * 100));
    }

    /**
     * Yemeni transcript conventions (اللائحة الموحدة): grade bands on the
     * percentage of the full mark — ممتاز ≥ 90, جيد جداً 80–89, جيد 65–79,
     * مقبول 50–64, ضعيف < 50. Used only when no course schema exists.
     */
    private function standardGradeLabel(int $percent): string
    {
        return match (true) {
            $percent >= 90 => __('general.grade_excellent'),
            $percent >= 80 => __('general.grade_very_good'),
            $percent >= 65 => __('general.grade_good'),
            $percent >= 50 => __('general.grade_acceptable'),
            default => __('general.grade_poor'),
        };
    }

    private function markColorFor(?Registration $record): string
    {
        if ($record === null || $record->grade_total === null) {
            return 'gray';
        }

        $course = $record->course;
        $pass = $course?->successMark();

        if ($pass === null) {
            return 'gray';
        }

        return (float) $record->grade_total >= $pass ? 'success' : 'danger';
    }

    private function attendanceRateFor(?Registration $record): ?string
    {
        $rate = $this->attendanceDataFor($record);

        if ($rate === null) {
            return null;
        }

        return $rate['percent'].'%';
    }

    private function attendanceNoteFor(?Registration $record): ?string
    {
        $rate = $this->attendanceDataFor($record);

        if ($rate === null) {
            return null;
        }

        return $rate['barred'] ? __('general.attendance_barred') : null;
    }

    private function attendanceColorFor(?Registration $record): string
    {
        $rate = $this->attendanceDataFor($record);

        if ($rate === null) {
            return 'gray';
        }

        return $rate['percent'] >= 75 ? 'success' : 'danger';
    }

    /**
     * Attendance per the Yemeni 75% rule: excused sessions stay in the
     * denominator but never count as absence; only the batch's own sessions
     * count (a repeated course in another batch is not mixed in).
     */
    private function attendanceDataFor(?Registration $record): ?array
    {
        if ($record === null || $record->batch === null) {
            return null;
        }

        $records = $record->attendanceRecords
            ->filter(fn ($item): bool => $item->session !== null
                && (int) $item->session->course_batch_id === (int) $record->course_batch_id)
            ->values();

        if ($records->isEmpty()) {
            return null;
        }

        $absent = $records->filter(fn ($item): bool => $item->status === 'absent')->count();

        $percent = (int) round(($records->count() - $absent) / $records->count() * 100);

        return [
            'percent' => $percent,
            'barred' => ($absent / $records->count()) * 100 > 25,
        ];
    }
}