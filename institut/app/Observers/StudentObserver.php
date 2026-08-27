<?php

namespace App\Observers;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentObserver
{
    public function creating(Student $student): void
    {
        if (empty($student->student_code)) {
            $next = (int) Student::withTrashed()->max('id') + 1;
            $student->student_code = 'STU-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        }
    }

    public function updating(Student $student): void
    {
        if ($student->isDirty('status')) {
            $original = $student->getOriginal('status');
            $new = $student->status;

            if ($original !== null && $original !== $new) {
                $allowed = Student::STATUS_TRANSITIONS[$original] ?? [];
                if (! in_array($new, $allowed, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => __('general.invalid_status_transition', ['from' => $original, 'to' => $new]),
                    ]);
                }
            }
        }
    }

    public function deleting(Student $student): void
    {
        if ($student->registrations()->whereIn('status', ['active', 'suspended'])->exists()) {
            throw new RuntimeException(__('general.cannot_delete_active_student'));
        }

        $balance = (float) $student->transactions()
            ->whereNull('voided_at')
            ->selectRaw("SUM(CASE WHEN type='charge' THEN amount WHEN type='payment' THEN -amount WHEN type='refund' THEN amount ELSE 0 END) AS bal")
            ->value('bal') ?? 0;

        if ($balance != 0) {
            throw new RuntimeException(__('general.cannot_delete_student_with_balance'));
        }
    }

    public function forceDeleting(Student $student): void
    {
        if ($student->transactions()->exists() || $student->registrations()->exists()) {
            throw new RuntimeException(__('general.cannot_delete_with_financial_history'));
        }
    }

    public function saved(Student $student): void
    {
        if ($student->isDirty('photo_path') && ($old = $student->getOriginal('photo_path'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function forceDeleted(Student $student): void
    {
        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }
    }
}
