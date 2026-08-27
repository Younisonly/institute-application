<?php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;

class LocaleSwitcherPlugin implements Plugin
{
    public function getId(): string
    {
        return 'locale-switcher';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::user-menu.before', fn (): string => view('plugins.locale-switcher')->render());
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return new static();
    }
}
