<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CourseBatch;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayrollPeriod;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceManagementService
{
    /**
     * Record or update an employee/teacher attendance entry.
     *
     * @param array $data
     * @param User|null $actor
     * @return StaffAttendance
     * @throws ValidationException
     */
    public function parseDate(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->startOfDay();
        }
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date)->startOfDay();
        }
        $str = trim((string) $date);
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $str)) {
            return Carbon::createFromFormat('d/m/Y', $str)->startOfDay();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str)) {
            return Carbon::parse(substr($str, 0, 10))->startOfDay();
        }

        return Carbon::parse($str)->startOfDay();
    }

    /**
     * Record or update an employee/teacher attendance entry.
     *
     * @param array $data
     * @param User|null $actor
     * @return StaffAttendance
     * @throws ValidationException
     */
    public function saveAttendance(array $data, ?User $actor = null): StaffAttendance
    {
        $actor = $actor ?? Auth::user();
        $date = $this->parseDate($data['date']);
        $dateStr = $date->toDateString();
        $staffId = (int) $data['staff_id'];
        $staff = Staff::findOrFail($staffId);

        // Rule 1: Past-date lock — past dates can ONLY be saved/edited by authorized roles (Admin / Accountant / Registrar)
        if ($date->lt(now()->startOfDay())) {
            if (! $actor || ! $actor->hasAnyRole(['admin', 'accountant', 'registrar'])) {
                throw ValidationException::withMessages([
                    'date' => __('general.past_date_edit_restricted_to_admin'),
                ]);
            }
        }

        // Rule 2: Closed Payroll Month protection — restricted to authorized roles (Admin / Accountant / Registrar)
        $month = $date->format('Y-m');
        $isClosed = StaffPayrollPeriod::query()
            ->where('staff_id', $staffId)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed) {
            if (! $actor || ! $actor->hasAnyRole(['admin', 'accountant', 'registrar'])) {
                throw ValidationException::withMessages([
                    'date' => __('general.cannot_modify_closed_payroll_session'),
                ]);
            }
        }

        return DB::transaction(function () use ($data, $staff, $staffId, $dateStr, $actor, $isClosed, $month) {
            $batchId = ! empty($data['course_batch_id']) ? (int) $data['course_batch_id'] : null;

            $wasExisting = false;
            // Find existing StaffAttendance record by ID or by staff_id + date
            if (! empty($data['id'])) {
                $staffAttendance = StaffAttendance::find($data['id']);
            } else {
                $staffAttendance = StaffAttendance::where('staff_id', $staffId)
                    ->where('date', $dateStr)
                    ->when($batchId, fn ($q) => $q->where('course_batch_id', $batchId))
                    ->first()
                    ?? StaffAttendance::where('staff_id', $staffId)
                        ->where('date', $dateStr)
                        ->first();

                if ($staffAttendance) {
                    $wasExisting = true;
                }
            }

            $oldStatus = $staffAttendance?->status;
            $oldHours = $staffAttendance?->hours_worked;

            if ($staffAttendance) {
                $staffAttendance->update([
                    'course_batch_id' => $batchId,
                    'status' => $data['status'],
                    'hours_worked' => (float) ($data['hours_worked'] ?? 0.0),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor?->id,
                ]);
            } else {
                $staffAttendance = StaffAttendance::create([
                    'staff_id' => $staffId,
                    'date' => $dateStr,
                    'course_batch_id' => $batchId,
                    'status' => $data['status'],
                    'hours_worked' => (float) ($data['hours_worked'] ?? 0.0),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor?->id,
                ]);
            }

            // If Staff is a teacher and a batch is selected, sync TeachingSession
            if ($staff->is_teacher && $batchId) {
                $batch = CourseBatch::findOrFail($batchId);

                // Enforce active studying batch status
                if (! in_array($batch->status, ['in_progress', 'open', 'scheduled'], true) && ! $batch->is_active) {
                    throw ValidationException::withMessages([
                        'course_batch_id' => __('general.batch_must_be_in_progress'),
                    ]);
                }

                $primaryTeacherId = ! empty($data['primary_teacher_id']) ? (int) $data['primary_teacher_id'] : ($batch->teacher_id ?? $staffId);
                $actualTeacherId = ! empty($data['actual_teacher_id']) ? (int) $data['actual_teacher_id'] : $staffId;

                $sessionStatus = match ($data['status']) {
                    'cancelled_session' => 'cancelled',
                    default => ($actualTeacherId !== $primaryTeacherId) ? 'substituted' : 'completed',
                };

                TeachingSession::updateOrCreate(
                    [
                        'course_batch_id' => $batchId,
                        'date' => $dateStr,
                        'period_id' => ! empty($data['period_id']) ? (int) $data['period_id'] : null,
                    ],
                    [
                        'primary_teacher_id' => $primaryTeacherId,
                        'actual_teacher_id' => $actualTeacherId,
                        'status' => $sessionStatus,
                        'planned_hours' => (float) ($data['planned_hours'] ?? $batch->daily_hours ?? 2.00),
                        'actual_hours' => (float) ($data['hours_worked'] ?? $batch->daily_hours ?? 2.00),
                        'cancellation_reason' => $data['cancellation_reason'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $actor?->id,
                    ]
                );
            }

            $auditAction = $isClosed ? 'staff_attendance.retroactive_edit' : ($wasExisting ? 'staff_attendance.updated' : 'staff_attendance.recorded');

            AuditLog::log($auditAction, StaffAttendance::class, $staffAttendance->id, [
                'staff_id' => $staffId,
                'date' => $dateStr,
                'status' => $data['status'],
                'hours_worked' => $data['hours_worked'] ?? 0.0,
                'old_status' => $oldStatus,
                'old_hours' => $oldHours,
                'is_closed_payroll' => $isClosed,
                'was_existing' => $wasExisting,
                'salary_month' => $month,
                'by_user_id' => $actor?->id,
            ]);

            $staffAttendance->setAttribute('is_closed_payroll_edit', $isClosed);
            $staffAttendance->setAttribute('was_already_recorded', $wasExisting);

            return $staffAttendance;
        });
    }

    /**
     * Delete an attendance record with security checks.
     *
     * @param StaffAttendance $attendance
     * @param User|null $actor
     * @return bool
     * @throws ValidationException
     */
    public function deleteAttendance(StaffAttendance $attendance, ?User $actor = null): bool
    {
        $actor = $actor ?? Auth::user();

        if ($attendance->date->lt(now()->startOfDay())) {
            if (! $actor || ! $actor->hasAnyRole(['admin', 'accountant', 'registrar'])) {
                throw ValidationException::withMessages([
                    'attendance' => __('general.past_date_edit_restricted_to_admin'),
                ]);
            }
        }

        $month = $attendance->date->format('Y-m');
        $isClosed = StaffPayrollPeriod::query()
            ->where('staff_id', $attendance->staff_id)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed) {
            if (! $actor || ! $actor->hasAnyRole(['admin', 'accountant', 'registrar'])) {
                throw ValidationException::withMessages([
                    'attendance' => __('general.cannot_modify_closed_payroll_session'),
                ]);
            }
        }

        return DB::transaction(function () use ($attendance, $actor, $isClosed, $month) {
            $auditAction = $isClosed ? 'staff_attendance.retroactive_deletion' : 'staff_attendance.deleted';

            AuditLog::log($auditAction, StaffAttendance::class, $attendance->id, [
                'staff_id' => $attendance->staff_id,
                'date' => $attendance->date->toDateString(),
                'salary_month' => $month,
                'is_closed_payroll' => $isClosed,
                'by_user_id' => $actor?->id,
            ]);

            return $attendance->delete();
        });
    }
}
