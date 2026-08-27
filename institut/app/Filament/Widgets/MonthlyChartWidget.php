<?php

namespace App\Filament\Widgets;

use App\Models\JournalEntryLine;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;

class MonthlyChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return __('general.monthly_income_expenses');
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    protected function getData(): array
    {
        $months = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $months[] = [$start->copy(), $start->copy()->endOfMonth()];
            $labels[] = $start->format('M y');
        }

        $income = [];
        $expenses = [];

        foreach ($months as [$from, $to]) {
            $income[] = (float) JournalEntryLine::query()
                ->whereHas('entry', function ($q) use ($from, $to): void {
                    $q->whereNull('voided_at')->whereBetween('date', [$from, $to]);
                })
                ->whereHas('account', fn ($q) => $q->where('type', 'income'))
                ->sum('credit');

            $expenses[] = (float) JournalEntryLine::query()
                ->whereHas('entry', function ($q) use ($from, $to): void {
                    $q->whereNull('voided_at')->whereBetween('date', [$from, $to]);
                })
                ->whereHas('account', fn ($q) => $q->where('type', 'expense'))
                ->sum('debit');
        }

        return [
            'datasets' => [
                [
                    'label' => __('general.income'),
                    'data' => $income,
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => __('general.expenses'),
                    'data' => $expenses,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
