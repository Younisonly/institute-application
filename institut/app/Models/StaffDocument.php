<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['staff_id', 'label', 'file_path'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
