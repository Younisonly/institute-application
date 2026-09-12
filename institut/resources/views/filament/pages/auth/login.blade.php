<x-filament-panels::page.simple>
    @php
        $institute = \App\Models\InstituteSetting::current();
    @endphp

    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'system';
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <style>
        /* Hide duplicate Filament brand header above card */
        .fi-simple-brand {
            display: none !important;
        }

        /* Move icon to the top */
        #login-icon-container {
            order: -1;
        }

        /* Eliminate browser autofill yellow overlay in Light & Dark modes */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
            caret-color: #0f172a !important;
            transition: background-color 50000s ease-in-out 0s !important;
            border-color: #cbd5e1 !important;
        }

        .dark input:-webkit-autofill,
        .dark input:-webkit-autofill:hover,
        .dark input:-webkit-autofill:focus,
        .dark input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0px 1000px #1e293b inset !important;
            -webkit-text-fill-color: #f8fafc !important;
            caret-color: #f8fafc !important;
            transition: background-color 50000s ease-in-out 0s !important;
            border-color: #334155 !important;
        }

        /* Modern Primary CTA Button Styling */
        .fi-btn-primary {
            background-image: linear-gradient(135deg, var(--inst-grad-from, #00B7EB), var(--inst-grad-to, #2DD4BF)) !important;
            border: none !important;
            box-shadow: 0 4px 14px 0 rgba(0, 183, 235, 0.3) !important;
            transition: all 0.2s ease-in-out !important;
        }
        .fi-btn-primary:hover {
            opacity: 0.94;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px 0 rgba(0, 183, 235, 0.4) !important;
        }
    </style>



    <x-slot name="heading">
        {{ __('general.welcome_to_tanzeem') }}
    </x-slot>

    <x-slot name="subheading">
        {{ __('general.tanzeem_description', ['institute' => $institute->localized_name]) }}
    </x-slot>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <div id="login-icon-container" class="mb-2 flex flex-col items-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-tr from-sky-500 to-teal-400 text-white shadow-lg shadow-sky-500/30 ring-4 ring-sky-500/10 dark:shadow-sky-500/20 dark:ring-sky-400/20">
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
