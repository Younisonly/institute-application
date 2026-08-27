<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Account extends Model
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'code', 'name_ar', 'name_en', 'type', 'parent_id', 'place_type', 'place_id', 'is_system', 'is_active',
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function place(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar);
    }

    public function getNameWithParentAttribute(): string
    {
        $name = $this->name;
        if ($this->parent !== null) {
            return $this->parent->name . ' → ' . $name;
        }

        return $name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function balance(?string $asOf = null): float
    {
        $lines = $this->lines()
            ->whereHas('entry', fn ($q) => $q->whereNull('voided_at')->when($asOf, fn ($q) => $q->whereDate('date', '<=', $asOf)))
            ->get(['debit', 'credit']);

        $balance = (float) ($lines->sum('debit') - $lines->sum('credit'));

        if (in_array($this->type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME], true)) {
            $balance = -$balance;
        }

        return round($balance, 2);
    }

    public function balanceFormatted(?string $asOf = null): string
    {
        return number_format($this->balance($asOf), 2, '.', '');
    }
}
