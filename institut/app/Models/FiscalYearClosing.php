<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYearClosing extends Model
{
    protected $fillable = ['year', 'net', 'journal_entry_id', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'net' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isVoided(): bool
    {
        return false;
    }
}
