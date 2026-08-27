<?php

namespace App\Observers;

use App\Models\SupplierTransaction;
use App\Services\FinancePostingService;

class SupplierTransactionObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(SupplierTransaction $transaction): void
    {
        $this->posting->postSupplierPayment($transaction);
    }

    public function updating(SupplierTransaction $transaction): void
    {
        if ($transaction->isDirty('voided_at') && $transaction->voided_at !== null) {
            $this->posting->reverseForDocument($transaction, $transaction->void_reason ?? __('general.void'));
        }
    }
}
