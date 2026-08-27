<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountType extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Human-readable label: if the name matches a legacy translation key, use it;
     * otherwise show the raw name so custom types display as-is.
     */
    public function getLabelAttribute(): string
    {
        $key = "general.discount_type_{$this->name}";
        $translated = __($key);

        return $translated !== $key ? $translated : $this->name;
    }
}
