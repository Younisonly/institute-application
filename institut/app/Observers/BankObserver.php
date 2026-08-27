<?php

namespace App\Observers;

use App\Models\Bank;
use App\Services\AccountService;

class BankObserver
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function created(Bank $bank): void
    {
        $this->accounts->ensureForPlace($bank);
    }
}
