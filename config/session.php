<?php

return [
    'driver'          => env('SESSION_DRIVER', 'file'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => false,
    'files'           => storage_path('framework/sessions'),
    'cookie'          => env('SESSION_COOKIE', 'meros_session'),
    'path'            => '/',
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE', is_ssl()),
    'http_only'       => true,
    'same_site'       => 'lax',
    'serialization'   => 'json',
];
