<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseBatch extends Model
{
    use SoftDeletes;

    public const LIFECYCLE_INACTIVE = 'inactive';
    public const LIFECYCLE_OPEN     = 'open';
    public const LIFECYCLE_RUNNING  = 'running';
    public const LIFECYCLE_FINISHED = 'finished';
    public const LIFECYCLE_FULL     = 'full';

    /**
     * Lifecycle status machine. 'full' is DERIVED (capacity reached),
     * never stored. Transitions live in CourseBatchService::transition().
     */
    public const STATUSES = [
        'draft',
        'scheduled',
        'open',
        'in_progress',
        'completed',
        'cancelled',
    ];

    /** @var array<string, string[]> from => allowed destinations */
    public const TRANSITIONS = [
        'draft' => ['scheduled', 'open', 'in_progress', 'cancelled'],
        'scheduled' => ['open', 'cancelled'],
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** Statuses that accept new registrations. */
    public const STATUSES_ENROLLABLE = ['open'];

    protected $attributes = ['status' => 'open'];

    protected $fillable = [
        'course_id',
        'name',
        'year',
        'enrollment_start',
        'enrollment_end',
        'start_date',
        'end_date',
        'finished_at',
        'capacity',
        'daily_hours',
        'total_hours',
        'working_days',
        'break_duration',
        'teacher_id',
        'notes',
        'is_active',
        'fee_schedule',
        'status',
        'cancelled_at',
        'cancelled_reason',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'daily_hours' => 'decimal:2',
            'total_hours' => 'integer',
            'working_days' => 'array',
            'break_duration' => 'integer',
            'is_active' => 'boolean',
            'fee_schedule' => 'array',
            'enrollment_start' => 'date',
            'enrollment_end' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Naming helpers
    // -------------------------------------------------------------------------

    /**
     * Sequence number of the next batch for a course (soft-deleted batches
     * still count, so the sequence never reuses a number).
     */
    public static function nextSequence(int $courseId): int
    {
        return static::query()
            ->withTrashed()
            ->where('course_id', $courseId)
            ->count() + 1;
    }

    /**
     * Sequence position of an existing batch within its course (by id),
     * so its identifier stays stable even after newer batches are created.
     */
    public static function sequenceOf(int $courseId, int $batchId): int
    {
        return (int) static::query()
            ->withTrashed()
            ->where('course_id', $courseId)
            ->where('id', '<=', $batchId)
            ->count();
    }

    /**
     * Default batch name built from the course id + the batch sequence,
     * e.g. "cou2-2". Editable — used only as a prefill.
     */
    public static function autoName(?int $courseId, ?int $sequence = null): string
    {
        return __('general.batch_name_auto', [
            'id' => $courseId ?? '?',
            'n' => $sequence ?? ($courseId !== null ? static::nextSequence($courseId) : 1),
        ]);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_id')->withTrashed();
    }

    public function periods(): BelongsToMany
    {
        return $this->belongsToMany(Period::class, 'course_batch_period')->orderBy('start_time');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function teachingSessions(): HasMany
    {
        return $this->hasMany(TeachingSession::class);
    }

    /**
     * The batch inherits the course's study period: start → expected end.
     * Explicit batch end_date wins; otherwise derived from start_date +
     * course months (last day of the final month, matching the old
     * month-based arithmetic).
     */
    public function getExpectedEndAttribute(): ?string
    {
        if ($this->end_date !== null) {
            return $this->end_date->toDateString();
        }

        $course = $this->course;
        if ($course === null || $this->start_date === null || $course->months === null) {
            return null;
        }

        return $this->start_date->copy()
            ->startOfMonth()
            ->addMonths(max(1, $course->months))
            ->subDay()
            ->toDateString();
    }

    // -------------------------------------------------------------------------
    // Lifecycle helpers
    // -------------------------------------------------------------------------

    /**
     * True when the batch accepts new registrations right now:
     * status must be 'open' + its course enrollable + within its own window + seats.
     */
    public function isEnrollmentOpen(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        $course = $this->course;
        if ($course === null || ! $course->isEnrollmentOpen()) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->enrollment_start !== null && $today->lt($this->enrollment_start)) {
            return false;
        }

        if ($this->enrollment_end !== null && $today->gt($this->enrollment_end)) {
            return false;
        }

        if ($this->end_date !== null && $this->end_date->isBefore($today)) {
            return false;
        }

        return true;
    }

    public function hasCapacityLeft(): bool
    {
        if ($this->capacity === null || $this->capacity <= 0) {
            return true;
        }

        $active = $this->registrations()
            ->whereIn('status', ['active', 'suspended'])
            ->count();

        return $active < $this->capacity;
    }

    public function getIsFullAttribute(): bool
    {
        return ! $this->hasCapacityLeft();
    }

    public function getSeatsRemainingAttribute(): ?int
    {
        if ($this->capacity === null || $this->capacity <= 0) {
            return null;
        }

        $active = $this->registrations()
            ->whereIn('status', ['active', 'suspended'])
            ->count();

        return max(0, $this->capacity - $active);
    }

    /**
     * Human-readable lifecycle status — derived, never stored.
     * Mirrors Course: inactive | open | running | finished | full.
     */
    public function getLifecycleStatusAttribute(): string
    {
        if ($this->status === 'completed') {
            return self::LIFECYCLE_FINISHED;
        }

        if ($this->status === 'cancelled') {
            return self::LIFECYCLE_INACTIVE;
        }

        if ($this->status === 'in_progress') {
            return self::LIFECYCLE_RUNNING;
        }

        $course = $this->course;

        if ($this->finished_at !== null) {
            return self::LIFECYCLE_FINISHED;
        }

        if ($this->status === 'draft' || $this->status === 'scheduled' || ! $this->is_active || ($course !== null && ! $course->is_active)) {
            return self::LIFECYCLE_INACTIVE;
        }

        $today = now()->startOfDay();

        $enrollmentEnded = $this->enrollment_end !== null && $today->gt($this->enrollment_end);
        $enrollmentNotOpen = $this->enrollment_start !== null && $today->lt($this->enrollment_start);
        $studyEnd = $this->end_date ?? ($this->expected_end !== null ? \Carbon\CarbonImmutable::parse($this->expected_end) : null);
        $studyEnded = $studyEnd !== null && $studyEnd->isBefore(now()->startOfDay());

        if ($enrollmentEnded) {
            return $studyEnded ? self::LIFECYCLE_FINISHED : self::LIFECYCLE_RUNNING;
        }

        if ($enrollmentNotOpen) {
            return self::LIFECYCLE_RUNNING;
        }

        if (! $this->hasCapacityLeft()) {
            return self::LIFECYCLE_FULL;
        }

        return self::LIFECYCLE_OPEN;
    }

    /**
     * Scope: batches a student can enroll in right now (open enrolment).
     */
    public function scopeEnrollable(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('course_batches.status', 'open')
            ->where('course_batches.is_active', true)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('course_batches.enrollment_start')
                  ->orWhere('course_batches.enrollment_start', '<=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('course_batches.enrollment_end')
                  ->orWhere('course_batches.enrollment_end', '>=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('course_batches.end_date')
                  ->orWhere('course_batches.end_date', '>=', $today);
            })
            ->whereHas('course', fn (Builder $q): Builder => (new Course)->scopeEnrollable($q));
    }

    /**
     * Keep the legacy is_active flag in sync with the stored status:
     * only 'open' batches accept registrations.
     */
    public function syncActiveFlag(): void
    {
        $active = $this->status === 'open';

        if ((bool) $this->is_active !== $active) {
            $this->is_active = $active;
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getPeriodsLabelAttribute(): string
    {
        $periods = $this->relationLoaded('periods') ? $this->periods : $this->periods()->get();

        if ($periods->isEmpty()) {
            return '—';
        }

        return $periods
            ->map(fn (Period $period): string => $period->option_label)
            ->implode(', ');
    }

    public function getOptionLabelAttribute(): string
    {
        $year = $this->year !== null ? ' • '.$this->year : '';

        return $this->name.$year;
    }

    /**
     * Enrollment window status derived purely from today vs enrollment dates.
     * Values: 'upcoming' | 'open' | 'closed' | 'no_window'
     */
    public function getEnrollmentWindowStatusAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->enrollment_start === null && $this->enrollment_end === null) {
            return 'no_window';
        }

        if ($this->enrollment_start !== null && $today->lt($this->enrollment_start)) {
            return 'upcoming';
        }

        if ($this->enrollment_end !== null && $today->gt($this->enrollment_end)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Study period status derived purely from today vs start/end dates.
     * Values: 'upcoming' | 'running' | 'finished' | 'unknown'
     */
    public function getStudyPeriodStatusAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->start_date === null) {
            return 'unknown';
        }

        if ($today->lt($this->start_date)) {
            return 'upcoming';
        }

        $end = $this->end_date
            ?? ($this->expected_end !== null ? \Carbon\Carbon::parse($this->expected_end)->startOfDay() : null);

        if ($end !== null && $today->gt($end)) {
            return 'finished';
        }

        return 'running';
    }
}