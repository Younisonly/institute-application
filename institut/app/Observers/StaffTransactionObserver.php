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
        if ($transaction->type === 'salary' && $transaction->salary_month && $transaction->staff_id) {
            $period = \App\Models\StaffPayrollPeriod::query()
                ->where('staff_id', $transaction->staff_id)
                ->where('salary_month', $transaction->salary_month)
                ->whereIn('status', ['approved', 'partially_paid'])
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $period) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'salary_month' => __('general.no_approved_payrolls_for_staff'),
                ]);
            }

            if (! $transaction->payroll_period_id) {
                $transaction->payroll_period_id = $period->id;
            }

            $attempted = (float) $transaction->amount + (float) $transaction->penalty_amount + (float) $transaction->advance_deduction_amount;
            $maxPayable = $period->remaining_payable;

            if (round($attempted, 2) > round($maxPayable + 0.009, 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => __('general.max_salary_exceeded', ['max' => number_format($maxPayable) . ' ' . __('general.currency')]),
                ]);
            }
        }
    }

    public function created(StaffTransaction $transaction): void
    {
        $this->posting->postStaffTransaction($transaction);

        if ($transaction->payroll_period_id && $transaction->payrollPeriod) {
            $transaction->payrollPeriod->recalculateStatus();
        }
    }

    public function updating(StaffTransaction $transaction): void
    {
        if ($transaction->isDirty('voided_at') && $transaction->voided_at !== null) {
            $this->posting->reverseForDocument($transaction, $transaction->void_reason ?? __('general.void'));
        }
    }

    public function updated(StaffTransaction $transaction): void
    {
        if ($transaction->payroll_period_id && $transaction->payrollPeriod) {
            $transaction->payrollPeriod->recalculateStatus();
        }
    }
}
