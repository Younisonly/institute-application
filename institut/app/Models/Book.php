<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'author', 'course_id', 'supplier_id', 'buy_price', 'sale_price',
        'stock_qty', 'min_stock_qty', 'low_stock_threshold', 'is_active', 'details', 'edition', 'isbn',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
            'min_stock_qty' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'buy_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'book_id');
    }

    public function registrations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
