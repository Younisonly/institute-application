<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'party_type', 'party_id', 'notes'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function party(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('party', 'party_type', 'party_id');
    }

    public function getPartyNameAttribute(): string
    {
        if ($this->party_id === null) {
            return '—';
        }

        return $this->party()->first()?->name ?? '—';
    }
}
