<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <x-filament::section :heading="__('general.payment_history_report')">
        <x-slot name="description">
            {{ __('general.total') }}: <b>{{ number_format($this->getReport()['total']) }} {{ __('general.currency') }}</b>
        </x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
