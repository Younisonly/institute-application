<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\FinancePostingService;
use Illuminate\Support\Facades\DB;

class ExpenseObserver
{
    private const EDITABLE_MONEY_FIELDS = ['amount', 'date', 'payment_method', 'bank_id', 'wallet_id', 'expense_category_id'];

    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(Expense $expense): void
    {
        $this->posting->postExpense($expense);
    }

    public function updating(Expense $expense): void
    {
        if ($expense->isDirty('voided_at') && $expense->voided_at !== null) {
            $this->posting->reverseForDocument($expense, $expense->void_reason ?? __('general.void'));

            return;
        }

        if ($expense->getOriginal('voided_at') !== null) {
            return;
        }

        if (collect(self::EDITABLE_MONEY_FIELDS)->contains(fn (string $field): bool => $expense->isDirty($field))) {
            DB::transaction(function () use ($expense): void {
                $this->posting->reverseForDocument($expense, __('general.expense_edited'));
                $this->posting->postExpense($expense);
            });
        }
    }
}