<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    @php($report = $this->getReport())

    <x-filament::section :heading="__('general.salary_sheet').' — '.$report['month']">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-gray-500 dark:text-gray-400">{{ __('general.fees') }}: <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($report['collected']) }} {{ __('general.currency') }}</span></span>
            <span class="text-gray-500 dark:text-gray-400">{{ __('general.total') }}: <span class="font-bold text-danger-600 dark:text-danger-400">{{ number_format($report['total']) }} {{ __('general.currency') }}</span></span>
        </div>
        {{ $this->table }}
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('general.per_hour_hint') }}</p>
    </x-filament::section>
</x-filament-panels::page>
