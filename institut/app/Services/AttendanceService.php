<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\CourseBatch;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance per batch session. A session auto-creates a 'present' record
 * for every ACTIVE registration of the batch (Yemeni roll-call practice:
 * attendance starts full and absences are marked). Only 'absent' counts as
 * unexcused absence — 'late' and 'excused' never hurt the student's rate.
 * Following the unified Yemeni student rules a student is barred from the
 * final exam when unexcused absence exceeds 25% of the sessions (i.e.
 * attendance below 75%). Records are never deleted — corrections stamp
 * corrected_at/corrected_by and keep an audit trail.
 */
class AttendanceService
{
    public function createSession(CourseBatch $batch, string $date, ?int $periodId, ?string $notes, int $userId): AttendanceSession
    {
        return DB::transaction(function () use ($batch, $date, $periodId, $notes, $userId): AttendanceSession {
            $session = AttendanceSession::create([
                'course_batch_id' => $batch->id,
                'date' => $date,
                'period_id' => $periodId,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $registrations = Registration::query()
                ->where('course_batch_id', $batch->id)
                ->where('status', 'active')
                ->pluck('id');

            foreach ($registrations as $registrationId) {
                AttendanceRecord::create([
                    'attendance_session_id' => $session->id,
                    'registration_id' => $registrationId,
                    'status' => 'present',
                ]);
            }

            AuditLog::log('attendance.session_created', AttendanceSession::class, $session->id, [
                'batch_id' => $batch->id,
                'date' => $date,
                'students' => $registrations->count(),
                'by' => $userId,
            ]);

            return $session;
        });
    }

    public function recordStatus(AttendanceSession $session, Registration $registration, string $status, int $userId, ?string $note = null, ?string $changeReason = null): AttendanceRecord
    {
        if (! in_array($status, AttendanceRecord::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => __('general.invalid_status'),
            ]);
        }

        return DB::transaction(function () use ($session, $registration, $status, $userId, $note, $changeReason): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('attendance_session_id', $session->id)
                ->where('registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            $previous = $record?->status;

            if ($record === null) {
                $record = AttendanceRecord::create([
                    'attendance_session_id' => $session->id,
                    'registration_id' => $registration->id,
                    'status' => $status,
                    'note' => $note,
                    'change_reason' => $changeReason,
                    'corrected_at' => now(),
                    'corrected_by' => $userId,
                ]);
            } else {
                $record->update([
                    'status' => $status,
                    'note' => $note !== null ? $note : $record->note,
                    'change_reason' => $changeReason !== null ? $changeReason : $record->change_reason,
                    'corrected_at' => $previous === $status && ! $note && ! $changeReason ? $record->corrected_at : now(),
                    'corrected_by' => $userId,
                ]);
            }

            AuditLog::change('attendance.recorded', AttendanceRecord::class, $record->id, $previous, $status, [
                'session_id' => $record->attendance_session_id,
                'registration_id' => $record->registration_id,
                'by' => $userId,
            ]);

            return $record;
        });
    }

    public function sessionStats(AttendanceSession $session): array
    {
        return AttendanceRecord::query()
            ->where('attendance_session_id', $session->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    /**
     * Sessions held so far for the registration's batch (or the given batch)
     * and how many of them the student missed unexcused.
     */
    public function absenceSummary(Registration $registration, ?CourseBatch $batch = null): array
    {
        $batch = $batch ?? $registration->batch;

        if ($batch === null) {
            return ['sessions' => 0, 'absent' => 0, 'late' => 0];
        }

        $records = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.course_batch_id', $batch->id)
            ->where('attendance_records.registration_id', $registration->id)
            ->pluck('attendance_records.status');

        return [
            'sessions' => $records->count(),
            'absent' => $records->filter(fn (string $s): bool => $s === 'absent')->count(),
            'late' => $records->filter(fn (string $s): bool => $s === 'late')->count(),
        ];
    }

    /**
     * Yemeni rule (75% model): a student cannot sit the final exam when
     * unexcused absence exceeds 25% of the sessions. 'excused' never counts.
     */
    public function isForbiddenFromExam(Registration $registration, ?CourseBatch $batch = null, float $thresholdPercent = 25.0): bool
    {
        $summary = $this->absenceSummary($registration, $batch);

        if ($summary['sessions'] === 0) {
            return false;
        }

        return ($summary['absent'] / $summary['sessions']) * 100 > $thresholdPercent;
    }
}