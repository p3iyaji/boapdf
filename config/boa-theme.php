<?php

declare(strict_types=1);

return [

    'preset' => env('BOA_THEME_PRESET', 'solar-stele'),

    'name' => env('BOA_THEME_NAME', env('APP_NAME', 'BOA PDF')),

    'tagline' => env('BOA_THEME_TAGLINE', 'Your library, illuminated'),

    'colors' => [
        'brand' => env('BOA_THEME_BRAND'),
        'accent' => env('BOA_THEME_ACCENT'),
        'canvas' => env('BOA_THEME_CANVAS'),
        'danger' => env('BOA_THEME_DANGER'),
        'success' => env('BOA_THEME_SUCCESS'),
    ],

    'fonts' => [
        'sans' => env('BOA_THEME_FONT_SANS', 'Source Sans 3'),
        'display' => env('BOA_THEME_FONT_DISPLAY', 'Cinzel'),
        'google' => env('BOA_THEME_GOOGLE_FONTS', true),
    ],

    'radius' => [
        'sm' => '0.5rem',
        'md' => '0.75rem',
        'lg' => '1rem',
        'xl' => '1.5rem',
    ],

    'dark_mode' => env('BOA_THEME_DARK_MODE', false),

];
