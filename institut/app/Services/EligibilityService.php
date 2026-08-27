<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Period;
use App\Models\Registration;

/**
 * Single source of truth for "can this student enter this course/batch".
 * The batch is the offering unit — course-level capacity NEVER blocks an
 * enrollment (it only filters option lists); the batch's own capacity does,
 * enforced atomically (lockForUpdate) inside the register transaction.
 * Returns a structured verdict so the UI can explain WHY, not just refuse.
 *
 * Blockers: course closed, batch missing/closed/full, duplicate enrollment
 * (same batch, or same course in another open registration), schedule
 * conflict with another active registration on an overlapping period.
 * Warnings: never fatal (unpaid balance on another enrollment).
 */
class EligibilityService
{
    public const STATUSES_ACTIVE = ['active', 'suspended', 'pending'];

    /**
     * @return array{eligible: bool, blockers: string[], warnings: string[], info: string[]}
     */
    public function check(int $studentId, Course $course, ?CourseBatch $batch, array $opts = []): array
    {
        $override = ! empty($opts['override']);
        $blockers = [];
        $warnings = [];
        $info = [];

        if (! $course->is_active || ! $course->isEnrollmentOpen()) {
            $blockers[] = __('general.enrollment_closed_error');
        }

        if ($batch !== null) {
            if ($batch->status === 'cancelled') {
                $blockers[] = __('general.batch_cancelled_error');
            } elseif ($batch->trashed() || ! $batch->isEnrollmentOpen()) {
                $blockers[] = __('general.batch_closed_error');
            }

            if ($batch->is_full) {
                $blockers[] = __('general.batch_full_error');
            }
        }

        if ($this->hasDuplicate($studentId, $course, $batch)) {
            $blockers[] = $batch !== null
                ? __('general.duplicate_batch_registration')
                : __('general.duplicate_registration');
        }

        foreach (app(ProgressionService::class)->missingRequiredPrerequisites($studentId, $course) as $label) {
            $blockers[] = __('general.missing_prerequisites', ['courses' => $label]);
        }

        $levelLabel = empty($opts['skip_level_gate']) ? $this->levelSequenceLabel($studentId, $course) : null;

        if ($levelLabel !== null) {
            $blockers[] = $levelLabel;
        }

        $conflictLabel = $this->scheduleConflictLabel($studentId, $this->periodIdsFor($batch));

        if ($conflictLabel !== null) {
            $blockers[] = $conflictLabel;
        }

        if ($this->hasUnpaidBalance($studentId)) {
            $warnings[] = __('general.unpaid_balance_warning');
        }

        if (! $batch?->periods_label) {
            $info[] = __('general.batch_no_periods_info');
        }

        if ($override && $blockers !== []) {
            $blockers = [];
            $info[] = __('general.override_applied');
        }

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'info' => $info,
        ];
    }

    /**
     * Duplicate policy (Yemeni practice — same course in DIFFERENT batches
     * is a normal re-offering and never a duplicate; the period overlap
     * check is the guard against real conflicts):
     *   - one open enrollment per (student, batch);
     *   - no open batchless enrollment for the same course within the
     *     course's enrollment window (legacy guard).
     * Completed/withdrawn/cancelled/closed/transferred enrollments never
     * block — repeating a finished course is allowed.
     */
    public function hasDuplicate(int $studentId, Course $course, ?CourseBatch $batch): bool
    {
        if ($batch !== null) {
            $inBatch = Registration::query()
                ->where('student_id', $studentId)
                ->where('course_batch_id', $batch->id)
                ->whereIn('status', self::STATUSES_ACTIVE)
                ->exists();

            if ($inBatch) {
                return true;
            }
        }

        $query = Registration::query()
            ->where('student_id', $studentId)
            ->where('course_id', $course->id)
            ->whereNull('course_batch_id')
            ->whereIn('status', self::STATUSES_ACTIVE);

        return $query->exists();
    }

    /**
     * True when any of the batch's periods overlaps a period of another
     * active registration's batch. YER practice: a student cannot be in two
     * lectures at the same time. The period lives on the batch only; a
     * batchless registration has no schedule and never conflicts.
     */
    public function periodsOverlap(?Period $a, ?Period $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $daysA = (array) $a->days;
        $daysB = (array) $b->days;

        if (array_intersect($daysA, $daysB) === []) {
            return false;
        }

        $aStart = strtotime($a->start_time);
        $aEnd = strtotime($a->end_time);
        $bStart = strtotime($b->start_time);
        $bEnd = strtotime($b->end_time);

        if ($aStart === false || $aEnd === false || $bStart === false || $bEnd === false) {
            return false;
        }

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * @return string|null the localized conflict reason, or null when clear
     */
    public function scheduleConflictLabel(int $studentId, array $newPeriodIds): ?string
    {
        if ($newPeriodIds === []) {
            return null;
        }

        $otherRegistrations = Registration::query()
            ->with(['batch.periods'])
            ->where('student_id', $studentId)
            ->whereIn('status', self::STATUSES_ACTIVE)
            ->whereHas('batch', fn ($q): \Illuminate\Database\Eloquent\Builder => $q->whereHas('periods'))
            ->get();

        $newPeriods = Period::query()->whereIn('id', $newPeriodIds)->get();

        foreach ($otherRegistrations as $registration) {
            foreach ($registration->batch?->periods ?? [] as $otherPeriod) {
                foreach ($newPeriods as $newPeriod) {
                    if ($this->periodsOverlap($newPeriod, $otherPeriod)) {
                        return __('general.schedule_conflict_error', [
                            'course' => $registration->course?->name ?? '',
                            'period' => $otherPeriod->option_label,
                        ]);
                    }
                }
            }
        }

        return null;
    }

    public function hasUnpaidBalance(int $studentId): bool
    {        return Registration::query()
            ->withTotals()
            ->where('student_id', $studentId)
            ->whereIn('status', self::STATUSES_ACTIVE)
            ->get()
            ->contains(fn (Registration $registration): bool => (float) $registration->balance > 0);
    }

    /**
     * Level sequencing (P1): once a student has attempts inside a program,
     * they may only enroll in the next level (max passed level + 1) or lower
     * — never skip ahead. Fresh students (no attempts in the program) are
     * free. Only courses carrying a curriculum level are gated; legacy
     * flat courses stay open. Override escapes (admin decision, audited).
     */
    private function levelSequenceLabel(int $studentId, Course $course): ?string
    {
        $level = $course->curriculumEntries()->first()?->level_no;

        if ($level === null || $level <= 1) {
            return null;
        }

        $attempts = \App\Models\Registration::query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['active', 'suspended', 'completed', 'closed'])
            ->whereHas('course', fn ($q): \Illuminate\Database\Eloquent\Builder => $q->where('program_type_id', $course->program_type_id))
            ->get();

        if ($attempts->isEmpty()) {
            return null;
        }

        $maxPassedLevel = 0;

        foreach ($attempts as $attempt) {
            $passed = ($attempt->result ?? 'pending') === 'pass'
                || ($attempt->grades['passed'] ?? false) === true;
            $attemptLevel = \App\Models\ProgramCourse::query()
                ->where('program_id', $course->program_type_id)
                ->where('course_id', $attempt->course_id)
                ->value('level_no');

            if ($passed && $attemptLevel !== null && $attemptLevel > $maxPassedLevel) {
                $maxPassedLevel = (int) $attemptLevel;
            }
        }

        if ($level > $maxPassedLevel + 1) {
            return __('general.level_sequence_error', [
                'level' => $level,
                'required' => $maxPassedLevel + 1,
            ]);
        }

        return null;
    }

    private function periodIdsFor(?CourseBatch $batch): array
    {
        if ($batch === null) {
            return [];
        }

        return $batch->periods()->pluck('periods.id')->map(fn (int $id): int => (int) $id)->all();
    }
}