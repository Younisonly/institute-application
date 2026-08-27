<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_GRADUATED = 'graduated';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_GRADUATED,
    ];

    public const STATUS_TRANSITIONS = [
        self::STATUS_ACTIVE => [self::STATUS_INACTIVE, self::STATUS_GRADUATED],
        self::STATUS_INACTIVE => [self::STATUS_ACTIVE, self::STATUS_GRADUATED],
        self::STATUS_GRADUATED => [],
    ];

    protected $fillable = [
        'student_code',
        'name',
        'gender',
        'birth_date',
        'phone',
        'whatsapp_phone',
        'national_id',
        'address',
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'education_level',
        'photo_path',
        'join_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StudentTransaction::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function enrollmentTransfers(): HasMany
    {
        return $this->hasMany(EnrollmentTransfer::class);
    }

    /**
     * Balance due = charges - payments + refunds (voided excluded).
     * Derived from transactions ONLY — never stored/mutated directly.
     */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->charges ?? 0)
            - (float) ($this->payments ?? 0)
            + (float) ($this->refunds ?? 0);
    }

    public function scopeWithBalance(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as charges' => fn ($q) => $q->where('type', 'charge')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as payments' => fn ($q) => $q->where('type', 'payment')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as refunds' => fn ($q) => $q->where('type', 'refund')->whereNull('voided_at')], 'amount');
    }
}
