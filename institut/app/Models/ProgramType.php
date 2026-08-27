<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramType extends Model
{
    use SoftDeletes;

    public const STUDY_SYSTEM_ANNUAL = 'annual';
    public const STUDY_SYSTEM_SEMESTER = 'semester';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = ['name', 'code', 'months_count', 'study_system', 'status', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'months_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function curriculum(): HasMany
    {
        return $this->hasMany(ProgramCourse::class, 'program_id')
            ->orderBy('level_no')
            ->orderBy('sort_order');
    }
}
