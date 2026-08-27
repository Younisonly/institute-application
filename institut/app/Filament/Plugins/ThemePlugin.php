<?php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;

class ThemePlugin implements Plugin
{
    public function getId(): string
    {
        return 'theme';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::head.start', fn (): string => $this->variables());
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return new static();
    }

    private function variables(): string
    {
        $theme = config('theme');
        $colors = $theme['colors'];
        $gradient = $theme['gradient'];

        $vars = implode("\n", [
            "--inst-primary: {$colors['primary']};",
            "--inst-grad-from: {$gradient['from']};",
            "--inst-grad-to: {$gradient['to']};",
        ]);

        return "<style>:root{{$vars}}</style>";
    }
}
