<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentTransaction extends Model
{
    public const TYPES = ['charge', 'payment', 'refund', 'transfer_credit', 'transfer_debit', 'write_off'];

    protected $fillable = [
        'student_id',
        'registration_id',
        'registration_item_id',
        'original_transaction_id',
        'type',
        'amount',
        'date',
        'description',
        'receipt_no',
        'method',
        'bank_id',
        'wallet_id',
        'cashbox_id',
        'transaction_ref',
        'income_account_id',
        'journal_entry_id',
        'voided_at',
        'void_reason',
        'voided_by',
        'created_by',
    ];

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'cashbox_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function registrationItem(): BelongsTo
    {
        return $this->belongsTo(RegistrationItem::class);
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(StudentTransaction::class, 'original_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(StudentTransaction::class, 'original_transaction_id')
            ->where('type', 'refund')
            ->whereNull('voided_at');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function getStudentTypeAttribute(): string
    {
        return Student::class;
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
