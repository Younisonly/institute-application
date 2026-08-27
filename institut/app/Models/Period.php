<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Period extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_ar',
        'name_en',
        'start_time',
        'end_time',
        'days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(CourseBatch::class, 'course_batch_period');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar);
    }

    public function getTimesLabelAttribute(): string
    {
        $start = $this->start_time !== null ? substr($this->start_time, 0, 5) : null;
        $end = $this->end_time !== null ? substr($this->end_time, 0, 5) : null;

        if ($start === null && $end === null) {
            return '';
        }

        return ($start ?? '?').' – '.($end ?? '?');
    }

    public function getOptionLabelAttribute(): string
    {
        return $this->times_label === '' ? $this->name : $this->name.' ('.$this->times_label.')';
    }

    public function getDaysLabelAttribute(): string
    {
        if (empty($this->days)) {
            return '—';
        }

        $labels = collect($this->days)->map(fn (string $day): string => __("general.{$day}"));

        return $labels->implode(', ');
    }
}
