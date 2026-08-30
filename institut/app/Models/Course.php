<?php

namespace App\Models;

use App\Models\CourseBatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    public const LIFECYCLE_INACTIVE   = 'inactive';
    public const LIFECYCLE_OPEN       = 'open';
    public const LIFECYCLE_RUNNING    = 'running';
    public const LIFECYCLE_FINISHED   = 'finished';
    public const LIFECYCLE_FULL       = 'full';

    protected $fillable = [
        'name',
        'program_type_id',
        'teacher_id',
        'months',
        'price',
        'hours_per_session',
        'number_of_sessions',
        'total_planned_hours',
        'working_days',
        'break_duration',
        'description',
        'capacity',
        'is_active',
        'full_mark',
        'success_marks',
        'grading_schema',
        'required_supplies',
    ];

    protected function casts(): array
    {
        return [
            'months'   => 'integer',
            'price'    => 'decimal:2',
            'hours_per_session' => 'decimal:2',
            'number_of_sessions' => 'integer',
            'total_planned_hours' => 'integer',
            'working_days' => 'array',
            'break_duration' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'full_mark' => 'integer',
            'success_marks' => 'integer',
            'grading_schema' => 'array',
            'required_supplies' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            $daily = (float) ($course->hours_per_session ?? 0);
            $total = (float) ($course->total_planned_hours ?? 0);
            if ($daily > 0 && $total > 0) {
                $course->number_of_sessions = (int) ceil($total / $daily);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function programType(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_id')->withTrashed();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(CourseBatch::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(CoursePrerequisite::class);
    }

    public function curriculumEntries(): HasMany
    {
        return $this->hasMany(ProgramCourse::class);
    }

    /**
     * The currently open batch of this course (or null). Used to auto-assign
     * registrations when the user does not pick a batch explicitly.
     */
    public function openBatch(): ?CourseBatch
    {
        return $this->batches()
            ->enrollable()
            ->orderBy('id')
            ->first();
    }

    // -------------------------------------------------------------------------
    // Lifecycle helpers
    // -------------------------------------------------------------------------

    /**
     * True when enrollment is open. Courses are templates — the enrollment
     * window lives on the batch (the enrollable unit), so the course is
     * enrollable whenever it is active.
     */
    public function isEnrollmentOpen(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * True when capacity is unlimited OR active registrations < capacity.
     */
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

    /**
     * Remaining seats (null = unlimited).
     */
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
     * Human-readable lifecycle status: inactive | open | full.
     * Derived — never stored. The run window itself lives on the batch.
     */
    public function getLifecycleStatusAttribute(): string
    {
        if (! $this->is_active) {
            return self::LIFECYCLE_INACTIVE;
        }

        if (! $this->hasCapacityLeft()) {
            return self::LIFECYCLE_FULL;
        }

        return self::LIFECYCLE_OPEN;
    }

    /**
     * Scope: courses that students can enroll in right now.
     * Active program + is_active + has seats (window lives on the batch).
     */
    public function scopeEnrollable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('programType', fn (Builder $q): Builder => $q->where('status', \App\Models\ProgramType::STATUS_ACTIVE))
            ->where(function (Builder $q): void {
                // no capacity limit OR still has seats
                $q->whereNull('capacity')
                  ->orWhere('capacity', '<=', 0)
                  ->orWhereRaw(
                      'capacity > (
                          SELECT COUNT(*) FROM registrations
                          WHERE registrations.course_id = courses.id
                          AND registrations.status IN (\'active\', \'suspended\')
                      )'
                  );
            });
    }

    // -------------------------------------------------------------------------
    // Grading
    // -------------------------------------------------------------------------

    /**
     * Minimum mark that counts as passing the course AND unlocks the next
     * course (progression gate). Explicit success_marks wins; otherwise half
     * of full_mark. Null when the course has no marks at all.
     */
    public function successMark(): ?int
    {
        if ($this->success_marks !== null && $this->success_marks > 0) {
            return $this->success_marks;
        }

        if ($this->full_mark !== null && $this->full_mark > 0) {
            return (int) ceil($this->full_mark / 2);
        }

        return null;
    }

    /**
     * Letter label for a mark using the course's grading schema
     * (entries sorted by their max boundary, ascending).
     */
    public function gradeFor(float $total): ?string
    {
        if (empty($this->grading_schema)) {
            return null;
        }

        $schema = collect($this->grading_schema)
            ->sortBy(fn (mixed $entry): float => (float) ($entry['max'] ?? 0))
            ->values();

        foreach ($schema as $entry) {
            if ($total <= (float) ($entry['max'] ?? 0)) {
                return (string) ($entry['label'] ?? '');
            }
        }

        return (string) ($schema->last()['label'] ?? null);
    }
}
