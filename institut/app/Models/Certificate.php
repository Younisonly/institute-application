<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'student_id',
        'program_id',
        'certificate_no',
        'title_ar',
        'title_en',
        'issue_date',
        'completion_date',
        'status',
        'voided_at',
        'void_reason',
        'verification_code',
        'issued_by',
        'earned_courses',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'completion_date' => 'date',
            'voided_at' => 'datetime',
            'earned_courses' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class, 'program_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VOIDED);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }
}