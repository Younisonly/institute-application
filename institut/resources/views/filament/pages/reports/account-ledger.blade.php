<x-filament-panels::page>
    <div class="print:hidden">{{ $this->form }}</div>

    <x-filament::section :heading="$this->getReport()['account']?->name ?? __('general.select_account')">
        {{ $this->table }}
        @if ($this->getReport()['account'])
            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                <span class="font-semibold">{{ __('general.balance') }}</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ number_format((float) $this->getReport()['total']) }}</span>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
