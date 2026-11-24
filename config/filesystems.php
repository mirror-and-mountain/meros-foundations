<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks'   => [
        'local' => ['driver' => 'local', 'root' => storage_path('app'), 'throw' => false],
        'uploads' => ['driver' => 'local', 'root' => wp_upload_dir()['basedir'], 'url' => wp_upload_dir()['baseurl'], 'visibility' => 'public', 'throw' => false],
    ],
    'links' => [],
];
