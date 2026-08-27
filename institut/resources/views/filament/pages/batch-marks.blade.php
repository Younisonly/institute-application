<x-filament-panels::page>
    <x-filament-panels::form>
        {{ $this->form }}
    </x-filament-panels::form>

    <x-filament::section>
        <div class="mb-4 flex items-center justify-between text-sm">
            <span class="text-gray-500 dark:text-gray-400">{{ __('general.batch_students') }}</span>
            <span class="font-bold">{{ __('general.marks_hint') }}</span>
        </div>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>