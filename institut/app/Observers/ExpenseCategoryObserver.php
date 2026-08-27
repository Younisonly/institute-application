<?php

namespace App\Observers;

use App\Models\ExpenseCategory;
use App\Services\AccountService;

class ExpenseCategoryObserver
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function created(ExpenseCategory $category): void
    {
        $this->accounts->ensureForExpenseCategory($category);
    }
}
