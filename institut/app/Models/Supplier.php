<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'address'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(StockMovement::class)->where('type', 'in')->whereNull('voided_at');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function getDebtAttribute(): float
    {
        return (float) ($this->purchases()->selectRaw('COALESCE(SUM(qty * unit_price), 0) as total')->value('total') ?? 0);
    }

    public function getPaidAttribute(): float
    {
        return (float) ($this->transactions()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total') ?? 0);
    }

    public function getBalanceAttribute(): float
    {
        return $this->debt - $this->paid;
    }
}
