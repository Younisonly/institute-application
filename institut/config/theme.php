<?php

/*
|--------------------------------------------------------------------------
| Theme Configuration
|--------------------------------------------------------------------------
|
| THE single source of truth for the app's look & feel.
| Change a color here and the whole system (Filament panel, login page,
| topbar, sidebar, buttons, badges) picks it up after `npm run build`
| (for CSS-level colors) or instantly for Filament component colors.
|
*/

return [

    /*
    | Main brand color — sky blue.
    */
    'primary' => '#00B7EB',

    /*
    | Semantic colors (Filament component colors).
    */
    'colors' => [
        'primary' => '#00B7EB',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444',
        'info' => '#0EA5E9',
    ],

    /*
    | Brand gradient — used on the login page, topbar accents, logo badges.
    */
    'gradient' => [
        'from' => '#00B7EB', // sky blue
        'to'   => '#2DD4BF', // teal
    ],

    /*
    | Neutral/gray palette name used by Filament (slate, gray, zinc, neutral, stone).
    */
    'gray' => 'slate',

    /*
    | Panel chrome.
    */
    'panel' => [
        'topbar_gradient' => true,   // gradient topbar background
        'sidebar'         => 'dark', // dark | light
    ],

    /*
    | Typography.
    */
    'font' => 'Cairo',
    'font_arabic' => 'Cairo', // used when locale is ar (falls back to Filament default if not installed)
];
