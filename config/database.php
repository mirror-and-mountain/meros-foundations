<?php

global $wpdb;

return [
    'default'     => env('DB_CONNECTION', 'wordpress'),
    'connections' => [
        'wordpress' => [
            'driver'         => 'mysql',
            'host'           => DB_HOST,
            'port'           => env('DB_PORT', 3306),
            'database'       => DB_NAME,
            'username'       => DB_USER,
            'password'       => DB_PASSWORD,
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => DB_CHARSET,
            'collation'      => $wpdb->collate ?? 'utf8mb4_unicode_ci',
            'prefix'         => $wpdb->prefix ?? 'wp_',
            'prefix_indexes' => true,
            'strict'         => true,
        ],
    ],
    'migrations' => ['table' => 'meros_migrations'],
];
