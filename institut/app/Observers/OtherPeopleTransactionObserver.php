<?php

namespace App\Observers;

use App\Models\OtherPeopleTransaction;
use App\Services\FinancePostingService;

class OtherPeopleTransactionObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(OtherPeopleTransaction $transaction): void
    {
        $this->posting->postOtherPersonTransaction($transaction);
    }

    public function updating(OtherPeopleTransaction $transaction): void
    {
        if ($transaction->isDirty('voided_at') && $transaction->voided_at !== null) {
            $this->posting->reverseForDocument($transaction, $transaction->void_reason ?? __('general.void'));
        }
    }
}
