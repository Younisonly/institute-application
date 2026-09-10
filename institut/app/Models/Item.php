<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'supplier_id',
        'unit',
        'stock_qty',
        'min_stock_qty',
        'low_stock_threshold',
        'purchase_price',
        'sale_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
            'min_stock_qty' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(Registration::class, 'registration_items')
            ->withPivot(['qty', 'unit_price', 'description']);
    }

    public function isLowStock(): bool
    {
        $threshold = $this->min_stock_qty !== null ? (float) $this->min_stock_qty : (float) $this->low_stock_threshold;

        return (float) $this->stock_qty <= $threshold;
    }

    public function scopeWithLowStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(fn (Builder $q2) => $q2->whereNotNull('min_stock_qty')->whereColumn('stock_qty', '<=', 'min_stock_qty'))
              ->orWhere(fn (Builder $q3) => $q3->whereNull('min_stock_qty')->whereColumn('stock_qty', '<=', 'low_stock_threshold'));
        })->where('is_active', true);
    }
}
