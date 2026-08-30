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

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(StaffSalaryHistory::class);
    }

    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(StaffPayrollPeriod::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function primaryTeachingSessions(): HasMany
    {
        return $this->hasMany(TeachingSession::class, 'primary_teacher_id');
    }

    public function actualTeachingSessions(): HasMany
    {
        return $this->hasMany(TeachingSession::class, 'actual_teacher_id');
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

    public function getEarnedSalaryForMonth(string $month): float
    {
        $period = $this->payrollPeriods()->where('salary_month', $month)->first();
        if ($period && in_array($period->status, ['approved', 'partially_paid', 'paid'], true)) {
            return (float) $period->gross_salary;
        }

        if ($this->salary_type === 'per_hour') {
            $sessionHours = (float) $this->actualTeachingSessions()
                ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                ->whereIn('status', ['completed', 'substituted'])
                ->sum('actual_hours');

            $attendanceHours = (float) $this->attendances()
                ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                ->whereIn('status', ['present', 'late'])
                ->sum('hours_worked');

            $hours = max($sessionHours, $attendanceHours);

            return $hours * (float) $this->salary_value;
        }

        if ($this->salary_type === 'percentage') {
            // Calculated percentage of collected registration fees for courses taught
            return (float) $this->salary_value; // fallback default
        }

        return (float) $this->salary_value;
    }

    /**
     * Outstanding advances = advances - repayments - deductions (voided excluded).
     */
    public function getOutstandingAdvanceAttribute(): float
    {
        $advances = array_key_exists('advances', $this->attributes)
            ? (float) $this->attributes['advances']
            : (float) $this->transactions()->where('type', 'advance')->whereNull('voided_at')->sum('amount');

        $repayments = array_key_exists('repayments', $this->attributes)
            ? (float) $this->attributes['repayments']
            : (float) $this->transactions()->where('type', 'repayment')->whereNull('voided_at')->sum('amount');

        $deductions = array_key_exists('advance_deductions', $this->attributes)
            ? (float) $this->attributes['advance_deductions']
            : (float) $this->transactions()->where('type', 'deduction')->whereNull('voided_at')->sum('amount')
              + (float) $this->transactions()->where('type', 'salary')->whereNull('voided_at')->sum('advance_deduction_amount');

        return max(0.0, $advances - $repayments - $deductions);
    }

    public function getTotalSalaryPaidAttribute(): float
    {
        return array_key_exists('salaries', $this->attributes)
            ? (float) $this->attributes['salaries']
            : (float) $this->transactions()->where('type', 'salary')->whereNull('voided_at')->sum('amount');
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
