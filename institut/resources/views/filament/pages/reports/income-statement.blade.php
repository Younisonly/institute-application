<x-filament-panels::page>
    <div class="print:hidden">{{ $this->form }}</div>

    @php($report = $this->getReport())

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_income') }}</div>
            <div class="text-xl font-bold text-success-600 dark:text-success-400">{{ number_format($report['totalIncome']) }} {{ __('general.currency') }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_expenses') }}</div>
            <div class="text-xl font-bold text-danger-600 dark:text-danger-400">{{ number_format($report['totalExpenses']) }} {{ __('general.currency') }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.net_profit') }}</div>
            <div class="text-xl font-bold {{ $report['net'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format($report['net']) }} {{ __('general.currency') }}</div>
        </div>
    </div>

    <x-filament::section :heading="__('general.income_statement')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
