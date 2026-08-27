<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
