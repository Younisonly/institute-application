<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Course;

class Staff extends Model
{
    use SoftDeletes;

    public const SALARY_TYPES = ['monthly', 'percentage', 'per_hour'];

    protected $fillable = [
        'name',
        'job_title_id',
        'phone',
        'photo_path',
        'contract_no',
        'salary_type',
        'salary_value',
        'percentage_value',
        'status',
        'is_teacher',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'salary_value' => 'decimal:2',
            'percentage_value' => 'decimal:2',
            'is_teacher' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StaffTransaction::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function courses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'staff_course_specialties')->withTimestamps();
    }

    /**
     * Outstanding advances = advances - repayments (voided excluded).
     */
    public function getOutstandingAdvanceAttribute(): float
    {
        return (float) ($this->advances ?? 0)
            - (float) ($this->repayments ?? 0)
            - (float) ($this->advance_deductions ?? 0);
    }

    public function getTotalSalaryPaidAttribute(): float
    {
        return (float) ($this->salaries ?? 0);
    }

    public function scopeWithAccount(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as salaries' => fn ($q) => $q->where('type', 'salary')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as advances' => fn ($q) => $q->where('type', 'advance')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as repayments' => fn ($q) => $q->where('type', 'repayment')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as advance_deductions' => fn ($q) => $q->where('type', 'deduction')->whereNull('voided_at')], 'amount');
    }
}
