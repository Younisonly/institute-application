<x-filament-panels::page>
    @php($report = $this->getReport())

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 print:hidden">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_debit') }}</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($report['totalDebit']) }} {{ __('general.currency') }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_credit') }}</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($report['totalCredit']) }} {{ __('general.currency') }}</div>
        </div>
    </div>

    <div class="print:hidden">{{ $this->form }}</div>

    <x-filament::section :heading="__('general.trial_balance')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
