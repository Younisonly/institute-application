<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeachingSession extends Model
{
    public const STATUSES = ['completed', 'cancelled', 'postponed', 'substituted'];

    protected $fillable = [
        'course_batch_id',
        'period_id',
        'primary_teacher_id',
        'actual_teacher_id',
        'date',
        'status',
        'planned_hours',
        'actual_hours',
        'cancellation_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'planned_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    public function courseBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class)->withTrashed();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function primaryTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'primary_teacher_id')->withTrashed();
    }

    public function actualTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actual_teacher_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceSession(): HasOne
    {
        return $this->hasOne(AttendanceSession::class, 'teaching_session_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isSubstituted(): bool
    {
        return $this->status === 'substituted' || ($this->primary_teacher_id !== $this->actual_teacher_id);
    }
}
