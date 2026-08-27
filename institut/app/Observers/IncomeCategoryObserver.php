<?php

namespace App\Observers;

use App\Models\IncomeCategory;
use App\Services\AccountService;

class IncomeCategoryObserver
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function created(IncomeCategory $category): void
    {
        $this->accounts->ensureForIncomeCategory($category);
    }
}
