<x-filament-panels::page>
    @php($report = $this->getReport())

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_assets') }}</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($report['totalAssets']) }} {{ __('general.currency') }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.liabilities_and_equity') }}</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($report['totalLiabilities']) }} {{ __('general.currency') }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.net_profit') }}</div>
            <div class="text-xl font-bold {{ $report['netIncome'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">{{ number_format($report['netIncome']) }} {{ __('general.currency') }}</div>
        </div>
    </div>

    <x-filament::section :heading="__('general.balance_sheet')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
