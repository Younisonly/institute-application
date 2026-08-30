<x-filament-panels::page>
    <div class="mb-6">
        <form wire:submit.prevent="submit">
            {{ $this->form }}
        </form>
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
