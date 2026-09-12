<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('general.welcome_to_tanzeem');
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return __('general.tanzeem_description', ['institute' => \App\Models\InstituteSetting::current()->localized_name]);
    }
}
