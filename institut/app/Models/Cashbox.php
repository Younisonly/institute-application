<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cashbox extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'keeper_id',
        'min_balance',
        'max_balance',
        'is_default',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'min_balance' => 'decimal:2',
            'max_balance' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function keeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keeper_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account(): MorphOne
    {
        return $this->morphOne(Account::class, 'place');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashboxShift::class, 'cashbox_id');
    }

    public function activeShift(): ?CashboxShift
    {
        return $this->shifts()->where('status', 'open')->latest()->first();
    }

    public function getAccountId(): ?int
    {
        return $this->account?->id;
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->name_ar ?: $this->name_en) : ($this->name_en ?: $this->name_ar);
    }

    public static function generateNextCode(): string
    {
        $counter = (int) (static::withTrashed()->max('id') ?? 0) + 1;

        do {
            $code = 'BOX-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $existsInCashbox = static::withTrashed()->where('code', $code)->exists();
            $existsInBank = Bank::withTrashed()->where('account_no', $code)->exists();
            $existsInAccount = Account::where('code', $code)->exists();

            if (! $existsInCashbox && ! $existsInBank && ! $existsInAccount) {
                return $code;
            }

            $counter++;
        } while (true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function balance(?string $asOf = null): float
    {
        return $this->account?->balance($asOf) ?? 0.0;
    }

    public function balanceFormatted(?string $asOf = null): string
    {
        return number_format($this->balance($asOf), 2, '.', '');
    }
}
