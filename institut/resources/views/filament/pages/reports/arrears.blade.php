<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold">{{ __('general.arrears') }}</span>
            <span class="text-sm font-bold text-danger-600 dark:text-danger-400">{{ number_format($this->getReport()['total']) }} {{ __('general.currency') }}</span>
        </div>
    </div>

    <x-filament::section :heading="__('general.arrears_report')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
