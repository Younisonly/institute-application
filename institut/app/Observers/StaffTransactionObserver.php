<?php

namespace App\Observers;

use App\Models\StaffTransaction;
use App\Services\FinancePostingService;

class StaffTransactionObserver
{
    public function __construct(private readonly FinancePostingService $posting)
    {
    }

    public function creating(StaffTransaction $transaction): void
    {
        if ($transaction->type === 'salary' && $transaction->salary_month) {
            $staff = \App\Models\Staff::find($transaction->staff_id);
            if ($staff && $staff->salary_type === 'monthly') {
                $base = (float) $staff->salary_value;
                $totalPaidThisMonth = (float) StaffTransaction::query()
                    ->where('staff_id', $staff->id)
                    ->whereIn('type', ['salary', 'deduction'])
                    ->whereNull('voided_at')
                    ->where('salary_month', $transaction->salary_month)
                    ->sum('amount');
                
                if (round($totalPaidThisMonth + (float) $transaction->amount, 2) > round($base, 2)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'amount' => __('general.max_salary_exceeded', ['max' => number_format($base)]),
                    ]);
                }
            }
        }
    }

    public function created(StaffTransaction $transaction): void
    {
        $this->posting->postStaffTransaction($transaction);
    }

    public function updating(StaffTransaction $transaction): void
    {
        if ($transaction->isDirty('voided_at') && $transaction->voided_at !== null) {
            $this->posting->reverseForDocument($transaction, $transaction->void_reason ?? __('general.void'));
        }
    }
}
