<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale')
            ?? session('locale');

        if ($locale && in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);

            if (session('locale') !== $locale) {
                session(['locale' => $locale]);
            }
        }

        return $next($request);
    }
}
