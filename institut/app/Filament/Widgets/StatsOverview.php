<?php

namespace App\Filament\Widgets;

use App\Models\OtherPeopleTransaction;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar', 'teacher']) ?? false;
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $stats = Cache::remember('dashboard_stats_refined', 60, function () use ($today): array {
            // Outstanding Arrears
            $arrearsRows = DB::table('student_transactions')
                ->whereNull('voided_at')
                ->groupBy('student_id')
                ->selectRaw("student_id, SUM(CASE WHEN type='charge' THEN amount WHEN type='payment' THEN -amount WHEN type='refund' THEN amount ELSE 0 END) AS bal")
                ->having('bal', '>', 0)
                ->get();

            // Today's Collections
            $paymentsQuery = StudentTransaction::query()
                ->where('type', 'payment')
                ->whereNull('voided_at')
                ->whereDate('date', $today);
            $peopleInQuery = OtherPeopleTransaction::query()
                ->where('type', 'in')
                ->whereNull('voided_at')
                ->whereDate('date', $today);

            return [
                'activeStudents' => (int) Student::query()->where('status', 'active')->count(),
                'activeRegistrations' => (int) Registration::query()->where('status', 'active')->count(),
                
                'outstandingTotal' => (float) $arrearsRows->sum('bal'),
                'outstandingCount' => $arrearsRows->count(),

                'todayCollectionsTotal' => (float) $paymentsQuery->sum('amount') + (float) $peopleInQuery->sum('amount'),
                'todayCollectionsCount' => $paymentsQuery->count() + $peopleInQuery->count(),
            ];
        });

        return [
            Stat::make(__('general.students'), number_format($stats['activeStudents']))
                ->description(__('general.active'))
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->url('/admin/students?tableFilters[status][value]=active'),
                
            Stat::make(__('general.registrations'), number_format($stats['activeRegistrations']))
                ->description(__('general.active'))
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info')
                ->url('/admin/registrations?tableFilters[status][value]=active'),
                
            Stat::make(__('general.today_collections'), number_format((float) $stats['todayCollectionsTotal']) . ' ' . __('general.currency'))
                ->description(number_format($stats['todayCollectionsCount']) . ' ' . __('general.collections_count'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
                
            Stat::make(__('general.arrears'), \App\Helpers\MoneyFormatter::formatStudentBalance((float) $stats['outstandingTotal']))
                ->description(__('general.outstanding_students', ['count' => number_format($stats['outstandingCount'])]))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url('/admin/arrears-report'),
        ];
    }
}
