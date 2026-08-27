<?php

namespace App\Observers;

use App\Models\Wallet;
use App\Services\AccountService;

class WalletObserver
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function created(Wallet $wallet): void
    {
        $this->accounts->ensureForPlace($wallet);
    }
}
