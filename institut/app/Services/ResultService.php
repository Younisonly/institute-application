<?php

namespace App\Services;

use App\Models\Registration;

/**
 * Single source of truth for academic verdict math, on the simple marks
 * architecture: ONE final mark per registration (grades.total) judged
 * against the course's success_marks; the flat grade snapshot
 * (registration.grades JSON) is regenerated from that mark so every
 * consumer (certificates, marks sheet, reports, grade_result) stays in
 * sync. There are no per-assessment components.
 */
class ResultService
{
    /**
     * Refresh the registration's flat grade snapshot from the stored mark.
     * The verdict (pass/fail, grade label) is re-derived from the course —
     * used when a finalized result is reopened for correction.
     */
    public function refreshGradeSnapshot(Registration $registration, int $userId): void
    {
        $course = $registration->course;
        $grades = is_array($registration->grades) ? $registration->grades : [];

        $total = isset($grades['total']) && $grades['total'] !== null && $grades['total'] !== ''
            ? (float) $grades['total']
            : null;

        if ($total === null) {
            return;
        }

        $grades['total'] = $total;
        $grades['full_mark'] = $course?->full_mark;
        $grades['grade'] = $course !== null ? $course->gradeFor($total) : null;
        $grades['passed'] = $course !== null
            && $course->successMark() !== null
            && $total >= $course->successMark();
        $grades['graded_at'] = now()->format('Y-m-d H:i');

        $registration->update(['grades' => $grades]);

        \App\Models\AuditLog::log('registration.grade_snapshot', Registration::class, $registration->id, [
            'total' => $total,
            'grade' => $grades['grade'],
            'passed' => $grades['passed'],
            'by' => $userId,
        ]);
    }
}