<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Registration;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batch lifecycle: status machine (draft|scheduled|open|in_progress|
 * completed|cancelled), opening the next round and finishing a run.
 * 'full' is derived (capacity), never stored. Cancelling requires a reason
 * and an empty batch — students must be completed/transferred first.
 * Courses are templates — the batch is the enrollable unit, so starting a new
 * batch never mutates the course's enrollment window or its lifecycle dates.
 */
class CourseBatchService
{
    /** Send a database notification to a role list, excluding the actor. */
    private function notifyRoles(int $actorId, array $roles, string $title, string $body, bool $danger = false): void
    {
        $recipients = User::query()
            ->role($roles)
            ->whereKeyNot($actorId)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->{$danger ? 'danger' : 'success'}()
            ->sendToDatabase($recipients);
    }
    /**
     * Move a batch along its status machine. Guards: destination must be a
     * known status and allowed from the current one; terminal statuses are
     * frozen. Cancelling additionally requires a reason and no open
     * registrations (active/suspended/pending) — seat release is inherent:
     * the batch stops accepting, registrations keep their history.
     */
    public function transition(CourseBatch $batch, string $to, int $userId, ?string $reason = null): void
    {
        if (! in_array($to, CourseBatch::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => __('general.invalid_status'),
            ]);
        }

        $allowed = CourseBatch::TRANSITIONS[$batch->status] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('general.batch_status_transition_error', [
                    'from' => __("general.batch_status_{$batch->status}"),
                    'to' => __("general.batch_status_{$to}"),
                ]),
            ]);
        }

        DB::transaction(function () use ($batch, $to, $userId, $reason): void {
            $previous = $batch->status;

            if ($to === 'cancelled') {
                if ($reason === null || trim((string) $reason) === '') {
                    throw ValidationException::withMessages([
                        'cancelled_reason' => __('general.batch_cancel_reason_required'),
                    ]);
                }

                $hasStudents = $batch->registrations()
                    ->whereIn('status', ['active', 'suspended', 'pending'])
                    ->exists();

                if ($hasStudents) {
                    throw ValidationException::withMessages([
                        'status' => __('general.batch_has_active_students'),
                    ]);
                }

                $batch->update([
                    'status' => 'cancelled',
                    'is_active' => false,
                    'cancelled_at' => now(),
                    'cancelled_reason' => $reason,
                    'cancelled_by' => $userId,
                ]);

                AuditLog::change('course_batch.cancelled', CourseBatch::class, $batch->id, $previous, 'cancelled', [
                    'reason' => $reason,
                    'by' => $userId,
                ]);

                $this->notifyRoles(
                    $userId,
                    ['admin', 'accountant'],
                    __('general.batch_cancelled_notification'),
                    __('general.batch_cancelled_notification_body', [
                        'name' => $batch->name,
                        'reason' => $reason,
                    ]),
                    danger: true,
                );

                return;
            }

            $batch->update([
                'status' => $to,
                'is_active' => $to === 'open',
            ]);

            AuditLog::change('course_batch.status_changed', CourseBatch::class, $batch->id, $previous, $to, [
                'by' => $userId,
            ]);
        });
    }

    /**
     * Registrations that count as "done" when a batch/course completes:
     * students still enrolled (active/suspended) OR students whose snapshotted
     * mark already passes the course's pass mark (grades.passed, JSON snapshot).
     */
    private function completionQuery($registrations)
    {
        return $registrations
            ->where(function (Builder $q): void {
                $q->whereIn('status', ['active', 'suspended'])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(grades, "$.passed")) = "1"');
            });
    }

    /**
     * Academic verdict for a registration at batch completion.
     * Uses the SNAPSHOTTED pass decision (grades.passed) when available —
     * never re-evaluates against the live course pass mark. Enrollments that
     * end without any recorded marks honestly get result=incomplete: the batch
     * ending alone never produces a pass.
     */
    private function resultFor(Registration $registration): string
    {
        $grades = $registration->grades;

        if (is_array($grades) && isset($grades['passed'])) {
            return $grades['passed'] ? 'pass' : 'fail';
        }

        if ($registration->grade_total !== null) {
            $passMark = $registration->course?->successMark();

            return $passMark !== null
                ? ($registration->grade_total >= $passMark ? 'pass' : 'fail')
                : 'incomplete';
        }

        return 'incomplete';
    }

    /**
     * Open the next round of a course as a new batch. The course's own
     * enrollment window is NOT touched; optionally, previously open batches
     * are closed for enrollment and older active registrations are closed.
     */
    public function startNewBatch(Course $course, array $data, int $userId): CourseBatch
    {
        return DB::transaction(function () use ($course, $data, $userId): CourseBatch {
            if ($data['close_previous_batch'] ?? false) {
                $course->batches()
                    ->where('is_active', true)
                    ->each(function (CourseBatch $batch) use ($userId): void {
                        $hasStudents = $batch->registrations()
                            ->whereIn('status', ['active', 'suspended', 'pending'])
                            ->exists();

                        $batch->update([
                            'status' => $hasStudents ? 'in_progress' : 'cancelled',
                            'is_active' => false,
                            'cancelled_at' => $hasStudents ? null : now(),
                            'cancelled_reason' => $hasStudents ? null : __('general.new_cohort_close_reason'),
                            'cancelled_by' => $hasStudents ? null : $userId,
                        ]);
                    });
            }

            if ($data['close_old_registrations'] ?? false) {
                $course->registrations()
                    ->whereIn('status', ['active', 'suspended'])
                    ->each(function (Registration $reg) use ($userId): void {
                        app(RegistrationService::class)->close(
                            $reg,
                            __('general.new_cohort_close_reason'),
                            $userId,
                        );
                    });
            }

            $startDate = $data['start_date'] ?: now()->toDateString();
            $year = substr((string) $startDate, 0, 4);
            $previous = $course->batches()->latest('id')->first();

            $batch = $course->batches()->create([
                'name' => $data['name'] ?: CourseBatch::autoName($course->id),
                'year' => $year,
                'enrollment_start' => $data['enrollment_start'] ?: null,
                'enrollment_end' => $data['enrollment_end'] ?: null,
                'start_date' => $startDate,
                'end_date' => $data['end_date'] ?: null,
                'capacity' => $data['capacity'] ?? $course->capacity,
                'teacher_id' => $data['teacher_id'] ?? ($previous?->teacher_id ?? $course->teacher_id),
                'status' => 'open',
                'is_active' => true,
            ]);

            AuditLog::log('course_batch.opened', CourseBatch::class, $batch->id, [
                'course_id' => $course->id,
                'year' => $year,
                'by' => $userId,
            ]);

            return $batch;
        });
    }

    /**
     * Finish a batch: complete its done students (enrolled OR already passed),
     * backfill the end date, deactivate the batch and audit the run.
     * Returns [completed, remaining] counts.
     */
    public function complete(CourseBatch $batch, int $userId): array
    {
        return DB::transaction(function () use ($batch, $userId): array {
            $completed = 0;

            $this->completionQuery($batch->registrations())
                ->each(function (Registration $reg) use (&$completed, $userId): void {
                    app(RegistrationService::class)->complete($reg, $userId, $this->resultFor($reg));
                    $completed++;
                });

            $remaining = $batch->registrations()
                ->whereIn('status', ['active', 'suspended'])
                ->count();

            $endDate = $batch->end_date?->toDateString() ?? $batch->expected_end ?? now()->toDateString();

            $batch->update([
                'end_date' => $endDate,
                'is_active' => false,
                'finished_at' => now(),
                'status' => 'completed',
            ]);

            AuditLog::log('course_batch.completed', CourseBatch::class, $batch->id, [
                'completed' => $completed,
                'remaining' => $remaining,
                'by' => $userId,
            ]);

            $this->notifyRoles(
                $userId,
                ['admin', 'accountant', 'registrar'],
                __('general.batch_completed_notification'),
                __('general.batch_completed_notification_body', [
                    'name' => $batch->name,
                    'completed' => $completed,
                    'remaining' => $remaining,
                ]),
            );

            return ['completed' => $completed, 'remaining' => $remaining];
        });
    }

    /**
     * Reopen a completed batch (admin-approved correction path): unfreezes
     * the batch (status back to in_progress, finished_at cleared), reopens
     * every registration the completion finalized (marks editable again,
     * result reset to pending, status restored to active) and audits the
     * whole rollback with the reason. Enrollment stays closed — this only
     * unlocks marks/attendance correction, never re-admits students.
     */
    public function reopen(CourseBatch $batch, int $userId, string $reason): void
    {
        DB::transaction(function () use ($batch, $userId, $reason): void {
            $fresh = CourseBatch::query()->withTrashed()->find($batch->id);

            if ($fresh === null || $fresh->finished_at === null) {
                throw ValidationException::withMessages([
                    'status' => __('general.reopen_error_not_completed'),
                ]);
            }

            $before = [
                'status' => $fresh->status,
                'finished_at' => $fresh->finished_at?->format('Y-m-d H:i:s'),
                'end_date' => $fresh->end_date?->toDateString(),
            ];

            $derivedEnd = $fresh->end_date !== null
                && $fresh->expected_end !== null
                && $fresh->end_date->toDateString() === $fresh->expected_end;

            $fresh->update([
                'status' => 'in_progress',
                'is_active' => false,
                'finished_at' => null,
                'end_date' => $derivedEnd ? null : $fresh->end_date,
            ]);

            $fresh->registrations()
                ->where('status', 'completed')
                ->get()
                ->each(function (Registration $registration) use ($userId, $reason): void {
                    if ($registration->result_finalized_at !== null) {
                        app(RegistrationService::class)->reopenResult($registration, $userId, $reason);
                    }

                    $registration->update([
                        'status' => 'active',
                        'closed_at' => null,
                    ]);
                });

            AuditLog::change('course_batch.reopened', CourseBatch::class, $fresh->id, $before, [
                'status' => 'in_progress',
                'finished_at' => null,
            ], [
                'by' => $userId,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Course-level completion: applies the same rule to every registration of
     * the course (including batchless ones) and finishes every batch that has
     * no students left. Returns [completed, remaining].
     */
    public function completeCourse(Course $course, int $userId): array
    {
        return DB::transaction(function () use ($course, $userId): array {
            $completed = 0;

            $this->completionQuery($course->registrations())
                ->each(function (Registration $reg) use (&$completed, $userId): void {
                    app(RegistrationService::class)->complete($reg, $userId, $this->resultFor($reg));
                    $completed++;
                });

            $course->batches()
                ->each(function (CourseBatch $batch) use ($userId): void {
                    $left = $batch->registrations()
                        ->whereIn('status', ['active', 'suspended'])
                        ->exists();

                    if (! $left && $batch->is_active) {
                        $batch->update([
                            'end_date' => $batch->end_date?->toDateString() ?? $batch->expected_end ?? now()->toDateString(),
                            'is_active' => false,
                            'finished_at' => now(),
                            'status' => 'completed',
                        ]);

                        AuditLog::log('course_batch.completed', CourseBatch::class, $batch->id, [
                            'completed' => 0,
                            'remaining' => 0,
                            'by' => $userId,
                            'via' => 'course',
                        ]);
                    }
                });

            $remaining = $course->registrations()
                ->whereIn('status', ['active', 'suspended'])
                ->count();

            AuditLog::log('course.completed', Course::class, $course->id, [
                'completed' => $completed,
                'remaining' => $remaining,
                'by' => $userId,
            ]);

            return ['completed' => $completed, 'remaining' => $remaining];
        });
    }
}