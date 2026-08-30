<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffPayrollPeriod extends Model
{
    public const STATUSES = ['draft', 'calculated', 'approved', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'staff_id',
        'salary_month',
        'start_date',
        'end_date',
        'base_salary',
        'worked_hours',
        'additions_amount',
        'penalties_amount',
        'advance_deduction_amount',
        'gross_salary',
        'net_salary',
        'status',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'worked_hours' => 'decimal:2',
            'additions_amount' => 'decimal:2',
            'penalties_amount' => 'decimal:2',
            'advance_deduction_amount' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StaffTransaction::class, 'payroll_period_id');
    }

    public function isVoided(): bool
    {
        return false;
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) StaffTransaction::query()
            ->where('staff_id', $this->staff_id)
            ->where('type', 'salary')
            ->where('salary_month', $this->salary_month)
            ->whereNull('voided_at')
            ->sum('amount');
    }

    public function getRemainingPayableAttribute(): float
    {
        return max(0.0, (float) $this->net_salary - $this->total_paid);
    }

    public function recalculateStatus(): void
    {
        if ($this->status === 'cancelled' || $this->status === 'draft') {
            return;
        }

        $paid = $this->total_paid;
        $net = (float) $this->net_salary;

        if ($paid >= $net && $net > 0) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partially_paid';
        } else {
            $newStatus = 'approved';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}
