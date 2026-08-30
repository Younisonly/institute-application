<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierTransaction extends Model
{
    protected $fillable = [
        'supplier_id', 'type', 'amount', 'date', 'method', 'bank_id', 'wallet_id', 'cashbox_id', 'transaction_ref',
        'reference', 'description', 'receipt_no', 'voided_at', 'void_reason', 'voided_by', 'created_by', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'voided_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function getSupplierTypeAttribute(): string
    {
        return Supplier::class;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Void with reason — NEVER delete financial records.
     */
    public function void(string $reason, ?int $userId = null): void
    {
        $this->update([
            'voided_at' => now(),
            'void_reason' => $reason,
            'voided_by' => $userId ?? \Illuminate\Support\Facades\Auth::id(),
        ]);
    }
}
