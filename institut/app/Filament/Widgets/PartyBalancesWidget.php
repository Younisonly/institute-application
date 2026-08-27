<?php

namespace App\Filament\Widgets;

use App\Models\OtherPeopleTransaction;
use App\Models\StaffTransaction;
use App\Models\StudentTransaction;
use App\Models\SupplierTransaction;
use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartyBalancesWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('general.party_balances');
    }

    protected function getStats(): array
    {
        $students = (float) StudentTransaction::query()
            ->whereNull('voided_at')
            ->selectRaw("SUM(CASE WHEN type = 'charge' THEN amount WHEN type = 'payment' THEN -amount WHEN type = 'refund' THEN amount ELSE 0 END) AS balance")
            ->value('balance') ?? 0;

        $advances = (float) StaffTransaction::query()
            ->whereNull('voided_at')
            ->selectRaw("SUM(CASE WHEN type IN ('advance') THEN amount WHEN type IN ('repayment','deduction') THEN -amount ELSE 0 END) AS balance")
            ->value('balance') ?? 0;

        $debt = (float) StockMovement::query()
            ->where('type', 'in')
            ->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM(qty * unit_price), 0) AS total')
            ->value('total') ?? 0;

        $paid = (float) SupplierTransaction::query()
            ->where('type', 'payment')
            ->whereNull('voided_at')
            ->sum('amount');

        $suppliers = $debt - $paid;

        $others = (float) OtherPeopleTransaction::query()
            ->whereNull('voided_at')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN amount WHEN type = 'out' THEN -amount ELSE 0 END) AS balance")
            ->value('balance') ?? 0;

        return [
            Stat::make(__('general.students_balance'), \App\Helpers\MoneyFormatter::formatStudentBalance($students, true))->color('danger'),
            Stat::make(__('general.staff_advances'), \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance($advances, true))->color('warning'),
            Stat::make(__('general.suppliers_balance'), \App\Helpers\MoneyFormatter::formatSupplierBalance($suppliers, true))->color('danger'),
            Stat::make(__('general.others_balance'), \App\Helpers\MoneyFormatter::formatOtherPersonBalance($others, true))->color($others >= 0 ? 'success' : 'danger'),
        ];
    }
}
