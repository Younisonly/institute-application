<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'excused', 'cancelled_session'];

    protected $table = 'staff_attendances';

    protected $fillable = [
        'staff_id',
        'course_batch_id',
        'date',
        'status',
        'hours_worked',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours_worked' => 'decimal:2',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function courseBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
