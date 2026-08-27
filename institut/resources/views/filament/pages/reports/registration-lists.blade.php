<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <x-filament::section :heading="__('general.registrations')">
        <div class="mb-4 flex items-center justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">{{ __('general.balance') }}:</span>
            <span class="font-bold text-danger-600 dark:text-danger-400">{{ number_format($this->getReport()['totalBalance']) }} {{ __('general.currency') }}</span>
        </div>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
