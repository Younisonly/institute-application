<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationItem extends Model
{
    protected $fillable = [
        'registration_id',
        'item_id',
        'book_id',
        'qty',
        'unit_price',
        'description',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->book?->title ?? $this->item?->name ?? '—';
    }

    public function getIsBookAttribute(): bool
    {
        return $this->book_id !== null;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
