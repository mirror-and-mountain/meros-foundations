<?php

/*
 * Packages add their own connections at runtime, e.g.
 * config(['queue.connections.my_package' => ['driver' => 'database', 'table' => 'my_jobs', ...]]).
 * Run workers with `wp meros:queue work <connection> --stop-when-empty`.
 */
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync'     => ['driver' => 'sync'],
        'null'     => ['driver' => 'null'],
        'database' => [
            'driver'       => 'database',
            'connection'   => env('DB_QUEUE_CONNECTION'),
            'table'        => env('DB_QUEUE_TABLE', 'jobs'),
            'queue'        => env('DB_QUEUE', 'default'),
            'retry_after'  => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],
    ],
    'failed' => [
        'driver'   => env('QUEUE_FAILED_DRIVER', 'null'),
        'database' => env('DB_CONNECTION'),
        'table'    => 'failed_jobs',
    ],
];
