<?php

namespace App\Services;

use App\Models\InstituteSetting;
use Illuminate\Support\Facades\DB;

class ReceiptNumberService
{
    /**
     * Allocate the next sequential receipt number atomically.
     * Must be called inside a DB transaction.
     */
    public function next(): int
    {
        return DB::transaction(function (): int {
            $settings = InstituteSetting::query()->lockForUpdate()->firstOrFail();

            $number = $settings->receipt_next_no;
            $settings->increment('receipt_next_no');

            return $number;
        });
    }
}
