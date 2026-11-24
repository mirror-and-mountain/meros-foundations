<?php

return [
    'name'            => env('APP_NAME', get_bloginfo('name') ?: 'Meros'),
    'env'             => defined('WP_ENV') ? WP_ENV : env('WP_ENV', 'production'),
    'debug'           => defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
    'url'             => env('APP_URL', home_url()),
    'timezone'        => 'UTC',
    'locale'          => env('APP_LOCALE', get_locale()),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'cipher'          => 'AES-256-CBC',
    'key'             => env('APP_KEY'),
    'previous_keys'   => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'maintenance'     => ['driver' => 'file'],
];
