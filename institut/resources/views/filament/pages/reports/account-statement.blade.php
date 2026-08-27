<x-filament-panels::page>
    <div class="print:hidden">{{ $this->form }}</div>

    <x-filament::section
        :heading="$this->reportHeading()"
        :description="$this->reportSummary()"
    >
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>