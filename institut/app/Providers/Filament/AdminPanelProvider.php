<?php

namespace App\Providers\Filament;

use App\Filament\FontProviders\LocalFontProvider;
use App\Filament\Plugins\LocaleSwitcherPlugin;
use App\Filament\Plugins\ThemePlugin;
use App\Filament\Widgets\BatchesEndingSoonWidget;
use App\Filament\Widgets\MonthlyChartWidget;
use App\Filament\Widgets\PendingResultsWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\RegistrationsTrendWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\LowStockWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>
                @media print {
                    @page { margin: 0; }
                    body { padding: 10mm !important; }
                    .fi-sidebar, .fi-topbar, .fi-header-actions, .fi-ta-header-toolbar, .fi-ta-actions { display: none !important; }
                    .fi-main { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
                    main { padding: 0 !important; margin: 0 !important; }
                    .fi-page { padding: 0 !important; margin: 0 !important; }
                    body, html { background: white !important; }
                    .fi-ta-content { overflow: visible !important; border: none !important; box-shadow: none !important; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                }
            </style>'
        );
        Table::configureUsing(function (Table $table): void {
            $table
                ->emptyStateHeading(fn (): string => __('general.no_records'))
                ->emptyStateDescription('')
                ->paginationPageOptions([25, 50, 100])
                ->defaultPaginationPageOption(25);
        });
    }

    public function panel(Panel $panel): Panel
    {
        $theme = config('theme');
        $colors = [];

        foreach ($theme['colors'] as $name => $hex) {
            $colors[$name] = Color::hex($hex);
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->brandName(fn (): string => \App\Models\InstituteSetting::current()->localized_name)
            ->font($theme['font'], provider: LocalFontProvider::class)
            ->plugin(LocaleSwitcherPlugin::make())
            ->plugin(ThemePlugin::make())
            ->colors($colors)
            ->theme(asset('css/filament/admin/theme.css'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                StatsOverview::class,
                MonthlyChartWidget::class,
                RegistrationsTrendWidget::class,
                BatchesEndingSoonWidget::class,
                PendingResultsWidget::class,
                LowStockWidget::class,
                RecentActivityWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
