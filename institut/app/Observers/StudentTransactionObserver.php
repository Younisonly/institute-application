<?php

namespace App\Observers;

use App\Models\StudentTransaction;
use App\Services\FinancePostingService;

class StudentTransactionObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(StudentTransaction $transaction): void
    {
        $this->posting->postStudentTransaction($transaction);
        \App\Services\DashboardCacheService::flush();
    }

    public function updating(StudentTransaction $transaction): void
    {
        if ($transaction->isDirty('voided_at') && $transaction->voided_at !== null) {
            $this->posting->reverseForDocument($transaction, $transaction->void_reason ?? __('general.void'));
            \App\Services\DashboardCacheService::flush();
        }
    }
}
