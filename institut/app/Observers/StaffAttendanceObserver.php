<?php

namespace App\Observers;

use App\Models\StaffAttendance;
use App\Models\StaffPayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StaffAttendanceObserver
{
    public function updating(StaffAttendance $attendance): void
    {
        $user = Auth::user();
        $originalDate = Carbon::parse($attendance->getOriginal('date'))->startOfDay();

        if ($originalDate->lt(now()->startOfDay()) && ($user && ! $user->hasAnyRole(['admin', 'accountant', 'registrar']))) {
            throw new \RuntimeException(__('general.past_date_edit_restricted_to_admin'));
        }

        $month = $originalDate->format('Y-m');
        $isClosed = StaffPayrollPeriod::query()
            ->where('staff_id', $attendance->staff_id)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed && ($user && ! $user->hasAnyRole(['admin', 'accountant', 'registrar']))) {
            throw new \RuntimeException(__('general.cannot_modify_closed_payroll_session'));
        }
    }

    public function deleting(StaffAttendance $attendance): void
    {
        $user = Auth::user();
        $date = Carbon::parse($attendance->date)->startOfDay();

        if ($date->lt(now()->startOfDay()) && ($user && ! $user->hasAnyRole(['admin', 'accountant', 'registrar']))) {
            throw new \RuntimeException(__('general.past_date_edit_restricted_to_admin'));
        }

        $month = $date->format('Y-m');
        $isClosed = StaffPayrollPeriod::query()
            ->where('staff_id', $attendance->staff_id)
            ->where('salary_month', $month)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->exists();

        if ($isClosed && ($user && ! $user->hasAnyRole(['admin', 'accountant', 'registrar']))) {
            throw new \RuntimeException(__('general.cannot_modify_closed_payroll_session'));
        }
    }
}
