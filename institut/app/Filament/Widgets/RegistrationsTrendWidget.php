<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;

class RegistrationsTrendWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return __('general.registrations_trend');
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false;
    }

    protected function getData(): array
    {
        $labels = [];
        $counts = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $labels[] = $start->format('M y');
            $counts[] = (int) Registration::query()
                ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('general.registrations'),
                    'data' => $counts,
                    'borderColor' => '#00B7EB',
                    'backgroundColor' => 'rgba(0, 183, 235, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
