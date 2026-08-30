<?php

namespace App\Observers;

use App\Models\AttendanceSession;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\Auth;

class TeachingSessionObserver
{
    public function created(TeachingSession $session): void
    {
        $attSession = AttendanceSession::query()
            ->where('course_batch_id', $session->course_batch_id)
            ->where('date', $session->date)
            ->where('period_id', $session->period_id)
            ->first();

        if ($attSession) {
            if ($attSession->teaching_session_id !== $session->id) {
                $attSession->update(['teaching_session_id' => $session->id]);
            }
        } else {
            AttendanceSession::create([
                'teaching_session_id' => $session->id,
                'course_batch_id' => $session->course_batch_id,
                'date' => $session->date,
                'period_id' => $session->period_id,
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function updating(TeachingSession $session): void
    {
        $month = $session->date->format('Y-m');
        $isClosed = \App\Models\StaffPayrollPeriod::query()
            ->where('staff_id', $session->actual_teacher_id)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed) {
            throw new \RuntimeException(__('general.cannot_modify_closed_payroll_session'));
        }

        if ($session->wasChanged(['course_batch_id', 'date', 'period_id'])) {
            $attSession = AttendanceSession::query()
                ->where('course_batch_id', $session->course_batch_id)
                ->where('date', $session->date)
                ->where('period_id', $session->period_id)
                ->first();

            if ($attSession && $attSession->teaching_session_id !== $session->id) {
                $attSession->update(['teaching_session_id' => $session->id]);
            }
        }
    }

    public function deleting(TeachingSession $session): void
    {
        $month = $session->date->format('Y-m');
        $isClosed = \App\Models\StaffPayrollPeriod::query()
            ->where('staff_id', $session->actual_teacher_id)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed) {
            throw new \RuntimeException(__('general.cannot_modify_closed_payroll_session'));
        }
    }
}
