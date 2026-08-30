<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashboxShift extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_RECONCILED = 'reconciled';

    public const VARIANCE_NONE = 'none';

    public const VARIANCE_SURPLUS = 'surplus';

    public const VARIANCE_SHORTAGE = 'shortage';

    protected $fillable = [
        'shift_no',
        'cashbox_id',
        'user_id',
        'opened_at',
        'closed_at',
        'status',
        'opening_balance',
        'system_cash_in',
        'system_cash_out',
        'expected_closing_balance',
        'physical_cash_count',
        'variance_amount',
        'variance_type',
        'variance_notes',
        'journal_entry_id',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'system_cash_in' => 'decimal:2',
            'system_cash_out' => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'physical_cash_count' => 'decimal:2',
            'variance_amount' => 'decimal:2',
        ];
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'cashbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isVoided(): bool
    {
        return false;
    }
}
