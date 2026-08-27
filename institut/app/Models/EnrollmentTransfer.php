<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentTransfer extends Model
{
    protected $fillable = [
        'from_registration_id',
        'to_registration_id',
        'student_id',
        'from_course_id',
        'to_course_id',
        'from_batch_id',
        'to_batch_id',
        'reason',
        'balance_carried',
        'months_carried',
        'carry_items',
        'transferred_at',
        'transferred_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'balance_carried' => 'decimal:2',
            'months_carried' => 'integer',
            'carry_items' => 'boolean',
            'transferred_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function fromRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'from_registration_id');
    }

    public function toRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'to_registration_id');
    }

    public function fromCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'from_course_id');
    }

    public function toCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'to_course_id');
    }

    public function fromBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'from_batch_id');
    }

    public function toBatch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'to_batch_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
