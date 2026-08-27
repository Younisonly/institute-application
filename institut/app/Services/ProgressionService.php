<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CoursePrerequisite;
use App\Models\ProgramCourse;
use App\Models\ProgramType;
use App\Models\Registration;
use Illuminate\Support\Collection;

/**
 * Academic progression: prerequisite satisfaction and next-step
 * recommendations per the curriculum (Yemeni institutes sequence levels:
 * a course's plan lists its levels in order with sequential-course
 * prerequisites; 'recommended' prerequisites inform but never block).
 * All evaluation is attempt-based (registrations) — an edited course row
 * never silently changes what a student already accomplished.
 */
class ProgressionService
{
    private function attemptsFor(int $studentId): Collection
    {
        return Registration::query()
            ->with(['course', 'batch'])
            ->where('student_id', $studentId)
            ->get();
    }

    public function isPassingAttempt(Registration $registration): bool
    {
        if ($registration->result === 'pass') {
            return true;
        }

        $grades = is_array($registration->grades) ? $registration->grades : [];

        // Legacy snapshot: graded as passed but never finalized to a result.
        return ($grades['passed'] ?? false) === true;
    }

    public function coursePassed(int $studentId, int $courseId): bool
    {
        return $this->attemptsFor($studentId)
            ->contains(fn (Registration $registration): bool => (int) $registration->course_id === (int) $courseId
                && $this->isPassingAttempt($registration));
    }

    public function bestTotal(int $studentId, int $courseId): ?float
    {
        return $this->attemptsFor($studentId)
            ->filter(fn (Registration $registration): bool => (int) $registration->course_id === (int) $courseId
                && $registration->grade_total !== null)
            ->map(fn (Registration $registration): float => (float) $registration->grade_total)
            ->max();
    }

    /**
     * Attendance rate (%) of the student's last attempt that has sessions.
     * Excused records stay in the denominator (same as AttendanceService).
     * Null when the course has no attendance data yet.
     */
    public function attendanceRate(int $studentId, int $courseId): ?float
    {
        $attempts = $this->attemptsFor($studentId)
            ->filter(fn (Registration $registration): bool => (int) $registration->course_id === (int) $courseId)
            ->filter(fn (Registration $registration): bool => $registration->batch !== null)
            ->sortByDesc('id')
            ->values();

        foreach ($attempts as $attempt) {
            $records = AttendanceRecord::query()
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->where('attendance_sessions.course_batch_id', $attempt->course_batch_id)
                ->where('attendance_records.registration_id', $attempt->id)
                ->pluck('attendance_records.status');

            if ($records->isEmpty()) {
                continue;
            }

            $absent = $records->filter(fn (string $status): bool => $status === 'absent')->count();

            return ($records->count() - $absent) / $records->count() * 100;
        }

        return null;
    }

    /**
     * Whether a single prerequisite row is satisfied: the prerequisite course
     * must have a passing attempt; min_mark compares against the best total;
     * min_attendance_percent compares against the last attempt's attendance
     * (a missing attendance history never fails the student).
     */
    public function prerequisiteSatisfied(CoursePrerequisite $prerequisite, int $studentId): bool
    {
        if ($prerequisite->rule_type === CoursePrerequisite::RULE_RECOMMENDED) {
            return true;
        }

        $passed = $this->coursePassed($studentId, (int) $prerequisite->prerequisite_course_id);

        if (! $passed) {
            return false;
        }

        if ($prerequisite->min_mark !== null) {
            $best = $this->bestTotal($studentId, (int) $prerequisite->prerequisite_course_id);

            if ($best !== null && $best < (float) $prerequisite->min_mark) {
                return false;
            }
        }

        if ($prerequisite->min_attendance_percent !== null) {
            $rate = $this->attendanceRate($studentId, (int) $prerequisite->prerequisite_course_id);

            if ($rate !== null && $rate < (float) $prerequisite->min_attendance_percent) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human labels of the unsatisfied required prerequisites:
     *  - required rows → the prerequisite course name;
     *  - alt_group rows are grouped: "A or B" when none of the group passed.
     * Recommended prerequisites never block.
     */
    public function missingRequiredPrerequisites(int $studentId, Course $course): array
    {
        $grouped = $course->prerequisites()
            ->with('prerequisiteCourse')
            ->get()
            ->filter(fn (CoursePrerequisite $p): bool => $p->rule_type !== CoursePrerequisite::RULE_RECOMMENDED)
            ->groupBy(
                fn (CoursePrerequisite $p): string => $p->rule_type === CoursePrerequisite::RULE_ALT_GROUP
                    ? 'group:'.($p->group_no ?? 0)
                    : 'single:'.$p->id
            );

        $missing = [];

        foreach ($grouped as $rows) {
            /** @var \Illuminate\Support\Collection<int, CoursePrerequisite> $rows */
            $satisfied = $rows->contains(fn (CoursePrerequisite $p): bool => $this->prerequisiteSatisfied($p, $studentId));

            if ($satisfied) {
                continue;
            }

            $labels = $rows
                ->map(fn (CoursePrerequisite $p): string => (string) ($p->prerequisiteCourse?->name ?? '#'))
                ->unique()
                ->values();

            $missing[] = $labels->count() > 1
                ? implode(' or ', $labels->all())
                : (string) $labels->first();
        }

        return $missing;
    }

    public function prerequisitesSatisfied(int $studentId, Course $course): bool
    {
        return $this->missingRequiredPrerequisites($studentId, $course) === [];
    }

    /**
     * Graduation evaluation for a program (proposal §14): every curriculum
     * entry marked `is_required` must have at least one passing attempt.
     * Optional entries never gate (recommended-only curricula).
     *
     * @return array{
     *     eligible: bool,
     *     required: int,
     *     passed: int,
     *     missing: array<int, string>,
     *     balance: string
     * }
     */
    public function graduationEligible(int $studentId, ProgramType $program): array
    {
        $entries = $program->curriculum()->with('course')->get();

        $requiredEntries = $entries
            ->filter(fn (ProgramCourse $entry): bool => $entry->is_required && $entry->course !== null);

        $passedNames = $requiredEntries
            ->filter(fn (ProgramCourse $entry): bool => $this->coursePassed($studentId, (int) $entry->course_id))
            ->map(fn (ProgramCourse $entry): string => (string) $entry->course->name);

        $required = $requiredEntries->count();
        $passed = $passedNames->count();

        $missing = $requiredEntries
            ->filter(fn (ProgramCourse $entry): bool => ! $this->coursePassed($studentId, (int) $entry->course_id))
            ->map(fn (ProgramCourse $entry): string => (string) $entry->course->name)
            ->values()
            ->all();

        $balance = (string) \App\Models\Student::query()
            ->withBalance()
            ->find($studentId)
            ?->balance ?? '0';

        return [
            'eligible' => $required > 0 && $missing === [],
            'required' => $required,
            'passed' => $passed,
            'missing' => $missing,
            'balance' => $balance,
        ];
    }

    /**
     * Immutable snapshot of the best passing attempt per curriculum course,
     * written into the certificate so it never depends on later edits.
     *
     * @return array<int, array{
     *     course_id: int,
     *     course: string,
     *     batch: string,
     *     year: string,
     *     mark: string|null,
     *     result: string
     * }>
     */
    public function earnedCoursesSnapshot(int $studentId, ProgramType $program): array
    {
        $snapshot = [];

        foreach ($program->curriculum()->with('course')->get() as $entry) {
            if ($entry->course === null) {
                continue;
            }

            $attempts = $this->attemptsFor($studentId)
                ->filter(fn (Registration $registration): bool => (int) $registration->course_id === (int) $entry->course_id);

            $best = $attempts
                ->filter(fn (Registration $registration): bool => $this->isPassingAttempt($registration))
                ->sortByDesc(fn (Registration $registration): float => (float) $registration->grade_total)
                ->first();

            if ($best === null) {
                continue;
            }

            $snapshot[] = [
                'course_id' => (int) $best->course_id,
                'course' => (string) $best->course?->name,
                'batch' => (string) ($best->batch?->name ?? '—'),
                'year' => (string) ($best->batch?->year ?? '—'),
                'mark' => $best->grade_total !== null ? number_format((float) $best->grade_total, 2, '.', '') : null,
                'result' => (string) __('general.passed'),
            ];
        }

        return $snapshot;
    }

    /**
     * Eligible next steps per the student's programs' curricula:
     * courses at a level ≤ (highest passed level + 1) that are not passed
     * yet, each with its prerequisite status. Recommendations only — this
     * never gates anything by itself.
     *
     * @return array<int, array{
     *     program_name: string,
     *     program_id: int,
     *     course: Course,
     *     level_no: int,
     *     sort_order: int,
     *     credit_hours: string|null,
     *     missing: array<int, string>,
     *     satisfied: bool
     * }>
     */
    public function recommend(int $studentId): array
    {
        $attempts = $this->attemptsFor($studentId)->filter(fn (Registration $r): bool => $r->course !== null);
        $programIds = $attempts
            ->map(fn (Registration $r): ?int => $r->course?->program_type_id)
            ->filter()
            ->unique()
            ->values();

        if ($programIds->isEmpty()) {
            return [];
        }

        $recommendations = [];

        foreach ($programIds as $programId) {
            $program = \App\Models\ProgramType::withTrashed()->find($programId);

            if ($program === null) {
                continue;
            }

            $entries = $program->curriculum()->with('course')->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $passedLevels = $entries
                ->filter(fn (ProgramCourse $entry): bool => $entry->course !== null
                    && $this->coursePassed($studentId, (int) $entry->course_id))
                ->map(fn (ProgramCourse $entry): int => (int) $entry->level_no);

            $maxLevel = $passedLevels->max() ?? 0;

            foreach ($entries as $entry) {
                if ($entry->course === null) {
                    continue;
                }

                if ($this->coursePassed($studentId, (int) $entry->course_id)) {
                    continue;
                }

                if ((int) $entry->level_no > $maxLevel + 1) {
                    continue;
                }

                $missing = $this->missingRequiredPrerequisites($studentId, $entry->course);

                $recommendations[] = [
                    'entry_id' => (int) $entry->id,
                    'program_name' => (string) $program->name,
                    'program_id' => (int) $program->id,
                    'course' => $entry->course,
                    'level_no' => (int) $entry->level_no,
                    'sort_order' => (int) $entry->sort_order,
                    'credit_hours' => $entry->credit_hours,
                    'missing' => $missing,
                    'satisfied' => $missing === [],
                ];
            }
        }

        return $recommendations;
    }
}