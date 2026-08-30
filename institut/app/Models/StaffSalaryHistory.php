<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalaryHistory extends Model
{
    protected $fillable = [
        'staff_id',
        'salary_type',
        'salary_value',
        'percentage_value',
        'payment_frequency',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_value' => 'decimal:2',
            'percentage_value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
