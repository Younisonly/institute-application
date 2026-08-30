<x-filament-panels::page>
    @php
        $shift = $this->getActiveShift();
        $totals = $this->getShiftTotals();
    @endphp

    @if ($shift)
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('general.cashbox') }}</div>
                <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $shift->cashbox->name }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $shift->shift_no }}</div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('general.opening_balance') }}</div>
                <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($shift->opening_balance, 2) }} {{ __('general.currency') }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $shift->opened_at->format('d/m/Y H:i') }}</div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('general.system_cash_in') }} / {{ __('general.system_cash_out') }}</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    +{{ number_format($totals['cash_in'], 2) }} / <span class="text-rose-600 dark:text-rose-400">-{{ number_format($totals['cash_out'], 2) }}</span>
                </div>
                <div class="text-xs text-gray-400 mt-1">{{ __('general.currency') }}</div>
            </div>

            <div class="p-4 bg-primary-50 dark:bg-primary-950/40 rounded-xl shadow-sm border border-primary-200 dark:border-primary-800">
                <div class="text-sm font-medium text-primary-600 dark:text-primary-400">{{ __('general.expected_closing_balance') }}</div>
                <div class="text-2xl font-black text-primary-700 dark:text-primary-300 mt-1">{{ number_format($totals['expected'], 2) }} {{ __('general.currency') }}</div>
                <div class="text-xs text-primary-500 mt-1">{{ __('general.shift_status_open') }}</div>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">{{ __('general.payment_history') }}</h3>
            {{ $this->table }}
        </div>
    @else
        <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="inline-flex p-4 rounded-full bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 mb-4">
                <x-heroicon-o-exclamation-triangle class="w-8 h-8" />
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ __('general.no_open_shift') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">
                {{ __('general.no_open_shift_hint') }}
            </p>
        </div>
    @endif
</x-filament-panels::page>
