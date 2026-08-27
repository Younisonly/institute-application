<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramCourse extends Model
{
    protected $table = 'program_course';

    protected $fillable = [
        'program_id',
        'course_id',
        'level_no',
        'semester_no',
        'sort_order',
        'is_required',
        'credit_hours',
    ];

    protected function casts(): array
    {
        return [
            'level_no' => 'integer',
            'semester_no' => 'integer',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'credit_hours' => 'decimal:1',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class, 'program_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}