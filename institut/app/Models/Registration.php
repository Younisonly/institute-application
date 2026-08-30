<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    public const STATUSES = ['active', 'suspended', 'completed', 'withdrawn', 'cancelled', 'closed', 'transferred'];

    public const RESULTS = ['pending', 'pass', 'fail', 'incomplete', 'absent', 'withdrawn'];

    protected $fillable = [
        'student_id',
        'course_id',
        'course_batch_id',
        'price_snapshot',
        'original_price',
        'discount_amount',
        'discount_type',
        'start_month',
        'months_count',
        'status',
        'result',
        'result_finalized_at',
        'result_finalized_by',
        'transferred_to_id',
        'closed_at',
        'close_reason',
        'notes',
        'created_by',
        'grades',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'original_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'months_count' => 'integer',
            'closed_at' => 'datetime',
            'result_finalized_at' => 'datetime',
            'grades' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CourseBatch::class, 'course_batch_id')->withTrashed();
    }

    public function months(): HasMany
    {
        return $this->hasMany(RegistrationMonth::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RegistrationItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StudentTransaction::class);
    }

    public function transferredTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_to_id');
    }

    public function transferredFrom(): HasOne
    {
        return $this->hasOne(self::class, 'transferred_to_id', 'id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(\App\Models\AttendanceRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The eligibility-override audit entry (reason + who + when), or null.
     */
    public function eligibilityOverrideAudit(): ?\App\Models\AuditLog
    {
        return \App\Models\AuditLog::query()
            ->where('action', 'registration.eligibility_overridden')
            ->where('entity_type', self::class)
            ->where('entity_id', $this->id)
            ->latest('id')
            ->first();
    }

    /**
     * Balance on THIS registration = charges - payments + refunds (voided excluded).
     * Derived from transaction records only — never stored/mutated directly.
     */
    public function getBalanceAttribute(): float
    {
        $charged = array_key_exists('charged', $this->attributes)
            ? (float) $this->attributes['charged']
            : (float) $this->transactions()->whereIn('type', ['charge', 'transfer_debit'])->whereNull('voided_at')->sum('amount');

        $paid = array_key_exists('paid', $this->attributes)
            ? (float) $this->attributes['paid']
            : (float) $this->transactions()->whereIn('type', ['payment', 'transfer_credit'])->whereNull('voided_at')->sum('amount');

        $writtenOff = array_key_exists('written_off', $this->attributes)
            ? (float) $this->attributes['written_off']
            : (float) $this->transactions()->where('type', 'write_off')->whereNull('voided_at')->sum('amount');

        $refunded = array_key_exists('refunded', $this->attributes)
            ? (float) $this->attributes['refunded']
            : (float) $this->transactions()->where('type', 'refund')->whereNull('voided_at')->sum('amount');

        return $charged - $paid - $writtenOff + $refunded;
    }

    public function getExpectedEndAttribute(): string
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->start_month)
            ->addMonths($this->months_count - 1)
            ->format('Y-m');
    }

    public function getMonthsRemainingAttribute(): int
    {
        if (in_array($this->status, ['closed', 'transferred', 'completed', 'withdrawn', 'cancelled'], true)) {
            return 0;
        }

        $start = CarbonImmutable::createFromFormat('Y-m', $this->start_month);
        $elapsed = (int) floor($start->diffInMonths(CarbonImmutable::now()->startOfMonth()));

        return max(0, $this->months_count - max(0, $elapsed));
    }

    public function scopeWithTotals(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as charged' => fn ($q) => $q->whereIn('type', ['charge', 'transfer_debit'])->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as paid' => fn ($q) => $q->whereIn('type', ['payment', 'transfer_credit'])->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as written_off' => fn ($q) => $q->where('type', 'write_off')->whereNull('voided_at')], 'amount')
            ->withSum(['transactions as refunded' => fn ($q) => $q->where('type', 'refund')->whereNull('voided_at')], 'amount');
    }

    // -------------------------------------------------------------------------
    // Marks / grades
    // -------------------------------------------------------------------------

    /**
     * Keys in the grades JSON that carry result metadata rather than a
     * component score (component scores are stored under their label).
     */
    public const GRADE_META_KEYS = ['total', 'grade', 'passed', 'full_mark', 'graded_at'];

    private function gradeComponents(array $grades): array
    {
        return array_filter(
            $grades,
            fn (mixed $value, string|int $key): bool => ! in_array((string) $key, self::GRADE_META_KEYS, true)
                && is_numeric($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function hasGrade(): bool
    {
        if (! is_array($this->grades)) {
            return false;
        }

        return isset($this->grades['total'])
            && $this->grades['total'] !== null
            && $this->grades['total'] !== ''
            || $this->gradeComponents($this->grades) !== [];
    }

    /**
     * Final mark: explicit total wins; otherwise the sum of component scores.
     */
    public function getGradeTotalAttribute(): ?float
    {
        $grades = is_array($this->grades) ? $this->grades : [];

        if (isset($grades['total']) && $grades['total'] !== null && $grades['total'] !== '') {
            return (float) $grades['total'];
        }

        $components = $this->gradeComponents($grades);

        if ($components === []) {
            return null;
        }

        return (float) array_sum($components);
    }

    public function getGradedAtAttribute(): ?string
    {
        $grades = is_array($this->grades) ? $this->grades : [];

        return $grades['graded_at'] ?? null;
    }

    public function getGradeResultAttribute(): string
    {
        if (! $this->hasGrade()) {
            return 'not_graded';
        }

        $grades = $this->grades;

        if (isset($grades['passed'])) {
            return $grades['passed'] ? 'passed' : 'failed';
        }

        $passMark = $this->course?->successMark();

        if ($passMark === null || $this->grade_total === null) {
            return 'not_graded';
        }

        return $this->grade_total >= $passMark ? 'passed' : 'failed';
    }

    /**
     * Finalize a student's mark for this registration. Component scores, if
     * present, are kept; the result metadata (total, grade label, pass/fail)
     * is derived from the course's grading schema and snapshotted.
     */
    public function saveGrade(?float $total, ?int $userId = null): void
    {
        $course = $this->course;
        $grades = is_array($this->grades) ? $this->grades : [];

        $grades['total'] = $total ?? null;
        $grades['full_mark'] = $course?->full_mark;
        $grades['grade'] = $course !== null && $total !== null
            ? $course->gradeFor($total)
            : null;
        $grades['passed'] = $course !== null && $total !== null
            && $course->successMark() !== null
            && $total >= $course->successMark();
        $grades['graded_at'] = now()->format('Y-m-d H:i');

        $this->update(['grades' => $grades]);

        \App\Models\AuditLog::log('registration.graded', self::class, $this->id, [
            'total' => $total,
            'grade' => $grades['grade'],
            'passed' => $grades['passed'],
            'by' => $userId,
        ]);
    }

    /**
     * Save marks entered per grading-schema component (labels as keys).
     * Only numeric, non-meta keys count; the total is derived and snapshotted
     * via saveGrade(). An all-empty/zero set clears the grade verdict.
     */
    public function saveGradeComponents(array $grades, ?int $userId = null): void
    {
        unset($grades['__trigger']);

        $grades = array_filter(
            $grades,
            fn (mixed $value, string|int $key): bool => ! in_array((string) $key, self::GRADE_META_KEYS, true)
                && is_numeric($value),
            ARRAY_FILTER_USE_BOTH,
        );

        $total = (float) array_sum($grades);

        $this->update(['grades' => $grades]);

        $this->saveGrade($grades !== [] && $total > 0 ? $total : null, $userId);
    }
}
