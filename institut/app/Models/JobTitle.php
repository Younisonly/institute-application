<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTitle extends Model
{
    protected $fillable = ['name', 'notes'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
