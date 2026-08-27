<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTransaction extends Model
{
    protected $fillable = [
        'staff_id',
        'type',
        'amount',
        'date',
        'reference',
        'salary_month',
        'method',
        'bank_id',
        'wallet_id',
        'transaction_ref',
        'journal_entry_id',
        'description',
        'rate_snapshot',
        'hours_snapshot',
        'percentage_snapshot',
        'salary_type_snapshot',
        'voided_at',
        'void_reason',
        'voided_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate_snapshot' => 'decimal:2',
            'hours_snapshot' => 'decimal:2',
            'percentage_snapshot' => 'decimal:2',
            'date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function void(string $reason, ?int $userId = null): void
    {
        $this->update([
            'voided_at' => now(),
            'void_reason' => $reason,
            'voided_by' => $userId ?? \Illuminate\Support\Facades\Auth::id(),
        ]);
    }
}
