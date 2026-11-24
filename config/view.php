<?php

return [
    'paths' => array_values(array_unique([
        get_theme_file_path('resources/views'),
        get_parent_theme_file_path('resources/views'),
        resource_path('views'),
    ])),
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
