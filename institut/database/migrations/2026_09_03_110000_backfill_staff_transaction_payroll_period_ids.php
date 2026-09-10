<?php

use App\Models\StaffPayrollPeriod;
use App\Models\StaffTransaction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $transactions = StaffTransaction::query()
            ->where('type', 'salary')
            ->whereNull('payroll_period_id')
            ->whereNotNull('salary_month')
            ->get();

        foreach ($transactions as $tx) {
            $period = StaffPayrollPeriod::query()
                ->where('staff_id', $tx->staff_id)
                ->where('salary_month', $tx->salary_month)
                ->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->orderBy('created_at', 'asc')
                ->first();

            if ($period) {
                $tx->update(['payroll_period_id' => $period->id]);
            }
        }

        foreach (StaffPayrollPeriod::all() as $period) {
            $period->recalculateStatus();
        }
    }

    public function down(): void
    {
        // No-op backfill migration
    }
};
