<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <x-filament::section :heading="__('general.summary')">
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('general.total_income') }}</span>
                    <span class="text-sm font-bold text-success-600">{{ number_format($this->getIncomeTotal()) }} {{ __('general.currency') }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('general.total_expenses') }}</span>
                    <span class="text-sm font-bold text-danger-600">{{ number_format($this->getExpenseTotal()) }} {{ __('general.currency') }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('general.net_profit') }}</span>
                    <span class="text-sm font-bold {{ $this->getIncomeTotal() - $this->getExpenseTotal() >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        {{ number_format($this->getIncomeTotal() - $this->getExpenseTotal()) }} {{ __('general.currency') }}
                    </span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

