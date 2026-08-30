<?php

namespace App\Observers;

use App\Models\CourseBatch;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\Auth;

class CourseBatchObserver
{
    public function created(CourseBatch $batch): void
    {
        if ($batch->teacher_id) {
            TeacherAssignment::create([
                'staff_id' => $batch->teacher_id,
                'course_batch_id' => $batch->id,
                'role' => 'primary',
                'start_date' => $batch->start_date ?? now()->toDateString(),
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function updated(CourseBatch $batch): void
    {
        if ($batch->wasChanged('teacher_id')) {
            $oldTeacherId = $batch->getOriginal('teacher_id');

            if ($oldTeacherId) {
                TeacherAssignment::query()
                    ->where('course_batch_id', $batch->id)
                    ->where('staff_id', $oldTeacherId)
                    ->where('is_active', true)
                    ->update([
                        'end_date' => now()->toDateString(),
                        'is_active' => false,
                    ]);
            }

            if ($batch->teacher_id) {
                TeacherAssignment::create([
                    'staff_id' => $batch->teacher_id,
                    'course_batch_id' => $batch->id,
                    'role' => 'primary',
                    'start_date' => now()->toDateString(),
                    'is_active' => true,
                    'created_by' => Auth::id(),
                ]);
            }
        }
    }
}
