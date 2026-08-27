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
        'stock_qty', 'low_stock_threshold', 'is_active', 'details', 'edition', 'isbn',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
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
        return $this->stock_qty <= $this->low_stock_threshold;
    }

    public function scopeWithLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_qty', '<=', 'low_stock_threshold')->where('is_active', true);
    }
}
