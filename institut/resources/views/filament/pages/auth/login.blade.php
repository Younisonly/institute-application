<x-filament-panels::page.simple>
    @php
        $institute = \App\Models\InstituteSetting::current();
    @endphp

    <x-slot name="heading">
        {{ $institute->localized_name }}
    </x-slot>

    <x-slot name="subheading">
        {{ __('general.login_tagline') }}
    </x-slot>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <div class="mb-8 flex justify-center">
        <div
            class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600 ring-1 ring-primary-500/20 dark:bg-primary-500/15 dark:text-primary-400 dark:ring-primary-400/20"
        >
            <x-filament::icon icon="heroicon-o-academic-cap" class="h-9 w-9" />
        </div>
    </div>

    <x-filament-panels::form id="form" wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
