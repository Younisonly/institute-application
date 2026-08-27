<x-filament-panels::page>
    @php($preview = $this->getPreview())
    @php($closed = $this->isYearClosed())

    <x-filament::section :heading="__('general.fiscal_year_closing')">
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('general.year_closing_body') }}</p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 print:hidden">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.fiscal_year') }}</div>
                <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->selectedYear() }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_income') }}</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($preview['totalIncome']) }} {{ __('general.currency') }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_expenses') }}</div>
                <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($preview['totalExpenses']) }} {{ __('general.currency') }}</div>
            </div>
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.net_result') }}</div>
                <div class="text-xl font-bold {{ $preview['net'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">{{ number_format($preview['net']) }} {{ __('general.currency') }}</div>
            </div>
        </div>

        @if ($closed)
            <div class="mt-4">
                <span class="inline-flex items-center gap-x-1 rounded-full bg-warning-50 px-3 py-1 text-sm font-medium text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                    <x-heroicon-m-lock-closed class="h-4 w-4" />
                    {{ __('general.year_status_closed') }}
                </span>
            </div>
        @elseif (! $this->canCloseSelectedYear())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('general.year_not_completed_hint') }}</p>
        @endif
    </x-filament::section>

    <div class="print:hidden">{{ $this->form }}</div>

    <x-filament::section :heading="__('general.closing_preview')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
