<?php

namespace App\Policies;

use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;

class StaffAttendancePolicy
{
    /**
     * Determine whether the user can view any staff attendances.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accountant', 'registrar', 'teacher']);
    }

    /**
     * Determine whether the user can view the staff attendance.
     */
    public function view(User $user, StaffAttendance $staffAttendance): bool
    {
        return $user->hasAnyRole(['admin', 'accountant', 'registrar', 'teacher']);
    }

    /**
     * Determine whether the user can create staff attendances.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accountant', 'registrar']);
    }

    /**
     * Determine whether the user can update the staff attendance.
     */
    public function update(User $user, StaffAttendance $staffAttendance): bool
    {
        $date = Carbon::parse($staffAttendance->date)->startOfDay();

        if ($date->lt(now()->startOfDay())) {
            return $user->hasRole('admin');
        }

        return $user->hasAnyRole(['admin', 'accountant', 'registrar']);
    }

    /**
     * Determine whether the user can delete the staff attendance.
     */
    public function delete(User $user, StaffAttendance $staffAttendance): bool
    {
        $date = Carbon::parse($staffAttendance->date)->startOfDay();

        if ($date->lt(now()->startOfDay())) {
            return $user->hasRole('admin');
        }

        return $user->hasAnyRole(['admin', 'accountant']);
    }
}
