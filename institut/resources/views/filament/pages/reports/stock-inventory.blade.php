<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <x-filament::section :heading="__('general.stock_inventory_report')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
