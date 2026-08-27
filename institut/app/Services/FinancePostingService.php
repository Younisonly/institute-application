<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\OtherPeopleTransaction;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Models\Transfer;

/**
 * Posts a balanced journal entry for every money event (cash-basis double entry).
 * Charges (billing rows) are NOT posted — only actual cash movements.
 */
class FinancePostingService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountService $accounts,
    ) {
    }

    public function placeAccount(?string $method, ?int $bankId = null, ?int $walletId = null): Account
    {
        if (in_array($method, ['bank', 'transfer', 'cheque'], true) && $bankId) {
            $bank = \App\Models\Bank::find($bankId);
            if ($bank) {
                return $this->accounts->ensureForPlace($bank);
            }
        }
        if ($method === 'wallet' && $walletId) {
            $wallet = \App\Models\Wallet::find($walletId);
            if ($wallet) {
                return $this->accounts->ensureForPlace($wallet);
            }
        }

        return $this->accounts->cashAccount();
    }

    public function postStudentTransaction(StudentTransaction $transaction): void
    {
        if (in_array($transaction->type, ['charge', 'transfer_debit', 'transfer_credit', 'write_off'])) {
            return; // billing rows only (cash basis)
        }

        $place = $this->placeAccount($transaction->method, $transaction->bank_id, $transaction->wallet_id);
        $income = $transaction->incomeAccount ?? $this->accounts->account(AccountService::CODE_INCOME_COURSE_FEES);

        if ($transaction->type === 'payment') {
            $this->journal->post(
                lines: [
                    ['account_id' => $place->id, 'debit' => $transaction->amount],
                    ['account_id' => $income->id, 'credit' => $transaction->amount, 'party_type' => $transaction->student_type, 'party_id' => $transaction->student_id],
                ],
                date: $transaction->date->toDateString(),
                description: __('general.payment').' — '.$transaction->student?->name.' ['.$place->name.']',
                reference: $transaction->receipt_no ? '#'.$transaction->receipt_no : null,
                documentType: StudentTransaction::class,
                documentId: $transaction->id,
            );
        } elseif ($transaction->type === 'refund') {
            $this->journal->post(
                lines: [
                    ['account_id' => $income->id, 'debit' => $transaction->amount, 'party_type' => $transaction->student_type, 'party_id' => $transaction->student_id],
                    ['account_id' => $place->id, 'credit' => $transaction->amount],
                ],
                date: $transaction->date->toDateString(),
                description: __('general.refund').' — '.$transaction->student?->name.' ['.$place->name.']',
                reference: $transaction->receipt_no ? '#'.$transaction->receipt_no : null,
                documentType: StudentTransaction::class,
                documentId: $transaction->id,
            );
        }
    }

    public function postStaffTransaction(StaffTransaction $transaction): void
    {
        $place = $this->placeAccount($transaction->method, $transaction->bank_id, $transaction->wallet_id);

        $lines = match ($transaction->type) {
            'salary' => [
                ['account_id' => $this->accounts->account(AccountService::CODE_EXPENSE_SALARIES)->id, 'debit' => $transaction->amount],
                ['account_id' => $place->id, 'credit' => $transaction->amount, 'party_type' => $transaction->staff_type, 'party_id' => $transaction->staff_id],
            ],
            'advance' => [
                ['account_id' => $this->accounts->account(AccountService::CODE_STAFF_ADVANCES)->id, 'debit' => $transaction->amount],
                ['account_id' => $place->id, 'credit' => $transaction->amount, 'party_type' => $transaction->staff_type, 'party_id' => $transaction->staff_id],
            ],
            'repayment' => [
                ['account_id' => $place->id, 'debit' => $transaction->amount, 'party_type' => $transaction->staff_type, 'party_id' => $transaction->staff_id],
                ['account_id' => $this->accounts->account(AccountService::CODE_STAFF_ADVANCES)->id, 'credit' => $transaction->amount],
            ],
            'deduction' => [
                ['account_id' => $this->accounts->account(AccountService::CODE_EXPENSE_SALARIES)->id, 'debit' => $transaction->amount],
                ['account_id' => $this->accounts->account(AccountService::CODE_STAFF_ADVANCES)->id, 'credit' => $transaction->amount, 'party_type' => $transaction->staff_type, 'party_id' => $transaction->staff_id],
            ],
            default => [],
        };

        if (empty($lines)) {
            return;
        }

        $this->journal->post(
            lines: $lines,
            date: $transaction->date->toDateString(),
            description: __('general.'.str_replace('-', '_', $transaction->type)).' — '.$transaction->staff?->name.' ['.$place->name.']',
            reference: $transaction->reference,
            documentType: StaffTransaction::class,
            documentId: $transaction->id,
        );
    }

    public function postExpense(Expense $expense): void
    {
        $place = $this->placeAccount($expense->payment_method, $expense->bank_id, $expense->wallet_id);
        $expenseAccount = $expense->category?->account_id
            ? Account::find($expense->category->account_id)
            : $this->accounts->account(AccountService::CODE_EXPENSE_OTHER);

        $this->journal->post(
            lines: [
                ['account_id' => $expenseAccount->id, 'debit' => $expense->amount],
                ['account_id' => $place->id, 'credit' => $expense->amount],
            ],
            date: $expense->date->toDateString(),
            description: __('general.expense').' — '.$expense->category?->name.' ['.$place->name.']',
            documentType: Expense::class,
            documentId: $expense->id,
        );
    }

    public function postStockPurchase(StockMovement $movement): void
    {
        if ($movement->type !== 'in' || ! $movement->supplier_id || $movement->qty <= 0 || $movement->unit_price <= 0) {
            return;
        }

        $inventory = $this->accounts->account(
            $movement->book_id ? AccountService::CODE_INVENTORY_BOOKS : AccountService::CODE_INVENTORY_ITEMS
        );
        $payable = $this->accounts->account(AccountService::CODE_SUPPLIER_PAYABLE);
        $amount = $movement->qty * $movement->unit_price;

        $this->journal->post(
            lines: [
                ['account_id' => $inventory->id, 'debit' => $amount],
                ['account_id' => $payable->id, 'credit' => $amount, 'party_type' => $movement->supplier_type, 'party_id' => $movement->supplier_id],
            ],
            date: $movement->date->toDateString(),
            description: __('general.purchase').' — '.($movement->book?->title ?? $movement->item?->name),
            reference: $movement->reference,
            documentType: StockMovement::class,
            documentId: $movement->id,
        );
    }

    public function postStockSale(StockMovement $movement): void
    {
        if ($movement->type !== 'sold' || $movement->qty <= 0 || $movement->unit_price <= 0) {
            return;
        }

        $place = $this->placeAccount($movement->method ?? 'cash', $movement->bank_id, $movement->wallet_id);
        $income = $this->accounts->account(
            $movement->book_id ? AccountService::CODE_INCOME_BOOKS : AccountService::CODE_INCOME_ITEMS
        );
        $amount = $movement->qty * $movement->unit_price;

        $this->journal->post(
            lines: [
                ['account_id' => $place->id, 'debit' => $amount],
                ['account_id' => $income->id, 'credit' => $amount],
            ],
            date: $movement->date->toDateString(),
            description: __('general.stock_sold').' — '.($movement->book?->title ?? $movement->item?->name).' ['.$place->name.']',
            reference: $movement->reference,
            documentType: StockMovement::class,
            documentId: $movement->id,
        );
    }

    public function postSupplierPayment(SupplierTransaction $transaction): void
    {
        if ($transaction->type !== 'payment') {
            return;
        }

        $place = $this->placeAccount($transaction->method, $transaction->bank_id, $transaction->wallet_id);

        $this->journal->post(
            lines: [
                ['account_id' => $this->accounts->account(AccountService::CODE_SUPPLIER_PAYABLE)->id, 'debit' => $transaction->amount, 'party_type' => $transaction->supplier_type, 'party_id' => $transaction->supplier_id],
                ['account_id' => $place->id, 'credit' => $transaction->amount],
            ],
            date: $transaction->date->toDateString(),
            description: __('general.supplier_payment').' — '.$transaction->supplier?->name.' ['.$place->name.']',
            reference: $transaction->receipt_no ? '#'.$transaction->receipt_no : null,
            documentType: SupplierTransaction::class,
            documentId: $transaction->id,
        );
    }

    public function postOtherPersonTransaction(OtherPeopleTransaction $transaction): void
    {
        $place = $this->placeAccount($transaction->method, $transaction->bank_id, $transaction->wallet_id);

        if ($transaction->type === 'in') {
            $income = $transaction->incomeCategory?->account_id
                ? Account::find($transaction->incomeCategory->account_id)
                : $this->accounts->account(AccountService::CODE_INCOME_OTHER);

            $this->journal->post(
                lines: [
                    ['account_id' => $place->id, 'debit' => $transaction->amount, 'party_type' => $transaction->person_type, 'party_id' => $transaction->other_person_id],
                    ['account_id' => $income->id, 'credit' => $transaction->amount],
                ],
                date: $transaction->date->toDateString(),
                description: __('general.income').' — '.$transaction->person?->name.' ['.$place->name.']',
                reference: $transaction->receipt_no ? '#'.$transaction->receipt_no : null,
                documentType: OtherPeopleTransaction::class,
                documentId: $transaction->id,
            );
        } elseif ($transaction->type === 'out') {
            $expenseAccount = $transaction->expenseCategory?->account_id
                ? Account::find($transaction->expenseCategory->account_id)
                : $this->accounts->account(AccountService::CODE_EXPENSE_OTHER);

            $this->journal->post(
                lines: [
                    ['account_id' => $expenseAccount->id, 'debit' => $transaction->amount],
                    ['account_id' => $place->id, 'credit' => $transaction->amount, 'party_type' => $transaction->person_type, 'party_id' => $transaction->other_person_id],
                ],
                date: $transaction->date->toDateString(),
                description: __('general.payment_to').' '.$transaction->person?->name.' ['.$place->name.']',
                reference: $transaction->receipt_no ? '#'.$transaction->receipt_no : null,
                documentType: OtherPeopleTransaction::class,
                documentId: $transaction->id,
            );
        }
    }

    public function postTransfer(Transfer $transfer): void
    {
        $this->journal->post(
            lines: [
                ['account_id' => $transfer->to_account_id, 'debit' => $transfer->amount],
                ['account_id' => $transfer->from_account_id, 'credit' => $transfer->amount],
            ],
            date: $transfer->date->toDateString(),
            description: __('general.transfer').' — '.$transfer->fromAccount?->name.' → '.$transfer->toAccount?->name,
            reference: $transfer->reference,
            documentType: Transfer::class,
            documentId: $transfer->id,
        );
    }

    public function postOpeningBalance(int $placeAccountId, float $amount, ?int $userId = null): void
    {
        $this->journal->post(
            lines: [
                ['account_id' => $placeAccountId, 'debit' => $amount],
                ['account_id' => $this->accounts->account(AccountService::CODE_CAPITAL)->id, 'credit' => $amount],
            ],
            date: now()->toDateString(),
            description: __('general.opening_balance'),
            reference: 'opening-balance',
            userId: $userId,
        );
    }

    /**
     * Reverse the journal entry of a voided document.
     */
    public function reverseForDocument(object $document, string $reason, ?int $userId = null): void
    {
        $entry = $document->journalEntry
            ?? JournalEntry::query()
                ->where('document_type', get_class($document))
                ->where('document_id', $document->getKey())
                ->latest('id')
                ->first();

        if ($entry && ! $entry->isVoided()) {
            $this->journal->reverse($entry, $reason, $userId);
        }
    }
}
