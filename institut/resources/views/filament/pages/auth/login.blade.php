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

        .fi-btn-primary {
            background-image: linear-gradient(135deg, var(--inst-grad-from, #00B7EB), var(--inst-grad-to, #2DD4BF)) !important;
            border: none !important;
            box-shadow: 0 4px 14px 0 rgba(0, 183, 235, 0.3) !important;
        }
        .fi-btn-primary:hover {
            opacity: 0.94;
        }
    </style>

    <x-slot name="heading">
        {{ __('general.welcome_back') }}
    </x-slot>

    <x-slot name="subheading">
        {{ $institute->localized_name }} — {{ __('general.login_subtitle') }}
    </x-slot>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <div class="mb-6 flex flex-col items-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-tr from-sky-500 to-teal-400 text-white shadow-lg shadow-sky-500/30 ring-4 ring-sky-500/10">
            <x-filament::icon icon="heroicon-o-academic-cap" class="h-9 w-9" />
        </div>
    </div>

    <!-- Quick Demo Credentials Box -->
    <div 
        wire:click="fillDemoCredentials"
        class="mb-6 cursor-pointer rounded-xl border border-sky-500/20 bg-sky-50/60 p-3.5 text-center transition-all hover:bg-sky-100/80 hover:shadow-md dark:border-sky-400/20 dark:bg-sky-950/40 dark:hover:bg-sky-900/50"
    >
        <div class="flex items-center justify-center gap-2 text-xs font-bold text-sky-700 dark:text-sky-300">
            <x-heroicon-o-key class="h-4 w-4" />
            <span>{{ __('general.demo_credentials_hint') }}</span>
        </div>
        <div class="mt-1.5 flex items-center justify-center gap-3 text-xs font-mono text-gray-700 dark:text-gray-300">
            <span class="rounded bg-white/80 px-2 py-0.5 shadow-xs dark:bg-gray-800">admin@institute.local</span>
            <span class="text-gray-400">|</span>
            <span class="rounded bg-white/80 px-2 py-0.5 shadow-xs dark:bg-gray-800">admin123</span>
        </div>
        <p class="mt-1 text-[11px] text-sky-600/80 dark:text-sky-400/80">
            ({{ __('general.click_to_fill_demo') }})
        </p>
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
