<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web App Manifest (M15)
    |--------------------------------------------------------------------------
    |
    | Serialised to JSON by PwaController::manifest() and served at
    | /manifest.webmanifest. Laravel owns this rather than vite-plugin-pwa
    | (which has "manifest: false") so there is one source of truth, it can read
    | config('app.name'), and it is assertable in a feature test without
    | "npm run build" having been run.
    |
    | The theme colour is indigo-600 — the sidebar logo tile in layouts/app.blade.php.
    |
    */

    'name'             => env('APP_NAME', 'AssetWise') . ' — Enterprise Asset Management',
    'short_name'       => env('APP_NAME', 'AssetWise'),
    'description'      => 'Track, move, audit and maintain company assets from the floor.',

    // UF-18 step 3: the installed app opens straight onto the dashboard.
    'start_url'        => '/dashboard',
    'scope'            => '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'theme_color'      => '#4f46e5',
    'background_color' => '#f9fafb',

    'icons' => [
        [
            'src'   => '/icons/icon-192.png',
            'sizes' => '192x192',
            'type'  => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'   => '/icons/icon-512.png',
            'sizes' => '512x512',
            'type'  => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => '/icons/icon-maskable-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],

    'apple_touch_icon' => '/icons/apple-touch-icon-180.png',

];
