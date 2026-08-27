<?php

namespace App\Observers;

use App\Models\Transfer;
use App\Services\FinancePostingService;

class TransferObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function created(Transfer $transfer): void
    {
        $this->posting->postTransfer($transfer);
    }

    public function updating(Transfer $transfer): void
    {
        if ($transfer->isDirty('voided_at') && $transfer->voided_at !== null) {
            $this->posting->reverseForDocument($transfer, $transfer->void_reason ?? __('general.void'));
        }
    }
}
