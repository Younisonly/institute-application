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
            $staff = \App\Models\Staff::find($transaction->staff_id);
            if ($staff) {
                $period = \App\Models\StaffPayrollPeriod::firstOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'salary_month' => $transaction->salary_month,
                    ],
                    [
                        'start_date' => \Carbon\Carbon::parse($transaction->salary_month.'-01')->startOfMonth(),
                        'end_date' => \Carbon\Carbon::parse($transaction->salary_month.'-01')->endOfMonth(),
                        'base_salary' => $staff->salary_value ?? 0,
                        'gross_salary' => $staff->getEarnedSalaryForMonth($transaction->salary_month),
                        'net_salary' => $staff->getEarnedSalaryForMonth($transaction->salary_month),
                        'status' => 'approved',
                        'approved_at' => now(),
                    ]
                );

                if (! $transaction->payroll_period_id && $period) {
                    $transaction->payroll_period_id = $period->id;
                }

                $maxPayable = $period->remaining_payable;
                $attempted = (float) $transaction->amount + (float) $transaction->penalty_amount + (float) $transaction->advance_deduction_amount;

                if (round($attempted, 2) > round($maxPayable + 0.01, 2) && $maxPayable > 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'amount' => __('general.max_salary_exceeded', ['max' => number_format($maxPayable) . ' ' . __('general.currency')]),
                    ]);
                }
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
