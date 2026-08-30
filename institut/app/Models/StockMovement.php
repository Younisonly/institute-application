<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class StockMovement extends Model
{
    public const TYPES = ['in', 'issue', 'sold', 'damaged'];

    protected $fillable = [
        'item_id',
        'book_id',
        'supplier_id',
        'type',
        'qty',
        'unit_price',
        'method',
        'bank_id',
        'wallet_id',
        'cashbox_id',
        'transaction_ref',
        'date',
        'registration_item_id',
        'reference',
        'description',
        'journal_entry_id',
        'voided_at',
        'void_reason',
        'voided_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function getSupplierTypeAttribute(): string
    {
        return Supplier::class;
    }

    public function isBook(): bool
    {
        return $this->book_id !== null;
    }

    public function registrationItem(): BelongsTo
    {
        return $this->belongsTo(RegistrationItem::class);
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

    /**
     * Void with reason — NEVER delete stock history records.
     * Restores/reverses the physical stock so the shelf always agrees
     * with the ledger (in movements removed, outbound movements added back).
     */
    public function void(string $reason, ?int $userId = null): void
    {
        if ($this->voided_at !== null) {
            return;
        }

        $stock = $this->book_id !== null
            ? Book::query()->lockForUpdate()->find($this->book_id)
            : ($this->item_id !== null ? Item::query()->lockForUpdate()->find($this->item_id) : null);

        if ($stock !== null && $this->type === 'in' && $stock->stock_qty < $this->qty) {
            throw ValidationException::withMessages([
                'stock' => __('general.cannot_void_stock_in_insufficient_qty', [
                    'current' => $stock->stock_qty,
                    'required' => $this->qty,
                ]),
            ]);
        }

        $this->update([
            'voided_at' => now(),
            'void_reason' => $reason,
            'voided_by' => $userId ?? \Illuminate\Support\Facades\Auth::id(),
        ]);

        if ($stock !== null) {
            $sign = $this->type === 'in' ? -1 : 1;
            $stock->update(['stock_qty' => $stock->stock_qty + ($sign * $this->qty)]);
        }
    }
}
