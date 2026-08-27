<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'date', 'description', 'reference', 'document_type', 'document_id',
        'created_by', 'voided_at', 'void_reason', 'reversed_entry_id',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'voided_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function document(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('document', 'document_type', 'document_id');
    }

    public function getDebitTotalAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getCreditTotalAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }
}
