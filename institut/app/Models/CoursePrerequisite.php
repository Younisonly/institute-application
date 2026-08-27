<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePrerequisite extends Model
{
    public const RULE_REQUIRED = 'required';
    public const RULE_ALT_GROUP = 'alt_group';
    public const RULE_RECOMMENDED = 'recommended';

    protected $fillable = [
        'course_id',
        'prerequisite_course_id',
        'rule_type',
        'group_no',
        'min_mark',
        'min_attendance_percent',
    ];

    protected function casts(): array
    {
        return [
            'group_no' => 'integer',
            'min_mark' => 'decimal:2',
            'min_attendance_percent' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function prerequisiteCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'prerequisite_course_id')->withTrashed();
    }
}