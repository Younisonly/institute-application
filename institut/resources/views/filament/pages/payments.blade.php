<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="savePayment">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-filament::section :heading="__('general.today_collections')">
            <div class="text-2xl font-bold text-success-600">
                {{ number_format(App\Models\StudentTransaction::query()->where('type', 'payment')->whereNull('voided_at')->whereDate('date', now())->sum('amount') + App\Models\OtherPeopleTransaction::query()->where('type', 'in')->whereNull('voided_at')->whereDate('date', now())->sum('amount')) }} {{ __('general.currency') }}
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('general.today') }} · {{ now()->translatedFormat('d/m/Y') }}</p>
        </x-filament::section>

        <x-filament::section :heading="__('general.students_with_arrears')">
            <div class="text-2xl font-bold text-danger-600">
                {{ number_format(App\Models\Student::query()->withBalance()->get()->sum('balance')) }} {{ __('general.currency') }}
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('general.total_outstanding') }}</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('general.recent_payments')">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
