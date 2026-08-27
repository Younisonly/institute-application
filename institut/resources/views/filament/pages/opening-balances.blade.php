<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="postBalances">
        <x-filament::section :heading="__('general.opening_balance_hint')">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('general.opening_balance_body') }}</p>
            {{ $this->form }}
        </x-filament::section>
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>
