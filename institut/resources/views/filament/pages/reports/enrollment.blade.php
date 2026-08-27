<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="applyFilters">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    @php($report = $this->getReport())

    <div class="grid gap-4 md:grid-cols-5">
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.total') }}</div>
            <div class="text-2xl font-bold">{{ $report['total'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.active') }}</div>
            <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $report['active'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.suspended') }}</div>
            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $report['suspended'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.closed') }}</div>
            <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $report['closed'] }}</div>
        </div>
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('general.transfer') }}</div>
            <div class="text-2xl font-bold text-info-600 dark:text-info-400">{{ $report['transferred'] }}</div>
        </div>
    </div>

    <x-filament::section :heading="__('general.enrollment_report')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
