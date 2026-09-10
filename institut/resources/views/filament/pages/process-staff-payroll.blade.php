<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <x-filament::section :heading="__('general.process_staff_payroll').' — '.$this->selectedMonth()">
        {{ $this->table }}
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('general.per_hour_hint') }}</p>
    </x-filament::section>
</x-filament-panels::page>
