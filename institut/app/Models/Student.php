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
     * Balance due = charges - payments - written_off + refunds (voided excluded).
     * Derived from transactions ONLY — never stored/mutated directly.
     */
    public function getBalanceAttribute(): float
    {
        $charges = array_key_exists('charges', $this->attributes)
            ? (float) $this->attributes['charges']
            : (float) $this->transactions()->where('type', 'charge')->whereNull('voided_at')->sum('amount');

        $payments = array_key_exists('payments', $this->attributes)
            ? (float) $this->attributes['payments']
            : (float) $this->transactions()->where('type', 'payment')->whereNull('voided_at')->sum('amount');

        $writtenOff = array_key_exists('written_off', $this->attributes)
            ? (float) $this->attributes['written_off']
            : (float) $this->transactions()->where('type', 'write_off')->whereNull('voided_at')->sum('amount');

        $refunds = array_key_exists('refunds', $this->attributes)
            ? (float) $this->attributes['refunds']
            : (float) $this->transactions()->where('type', 'refund')->whereNull('voided_at')->sum('amount');

        return $charges - $payments - $writtenOff + $refunds;
    }

    public function scopeWithBalance(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as charges' => fn ($q) => $q->where('type', 'charge')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as payments' => fn ($q) => $q->where('type', 'payment')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as written_off' => fn ($q) => $q->where('type', 'write_off')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as refunds' => fn ($q) => $q->where('type', 'refund')->whereNull('voided_at')], 'amount');
    }
}
