<?php

namespace MM\Foundations;

use MM\Foundations\Config\Stubs;
use MM\Foundations\Console\Cli;
use MM\Foundations\WordPress\Runtime;

final class Foundations
{
    /**
     * Build the application without attaching it to WordPress.
     *
     * @param string[] $providers
     * @param string[] $stubs      extra config stub directories (later wins)
     */
    public static function create(string $root, array $providers = [], array $stubs = [], bool $wordpressRouting = true): Application
    {
        foreach ($stubs as $directory) {
            Stubs::register($directory);
        }

        $app = (new Application($root))
            ->usePaths(['storage' => self::storagePath()])
            ->withProviders($providers);

        foreach (['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app'] as $directory) {
            is_dir($path = $app->storagePath($directory)) || @mkdir($path, 0775, true);
        }

        $wordpressRouting && $app->handleWordPressRequests();

        return $app;
    }

    /** Create the application and attach it to the current WordPress request or WP-CLI process. */
    public static function boot(string $root, array $providers = [], array $stubs = [], bool $wordpressRouting = true): Application
    {
        $app = self::create($root, $providers, $stubs, $wordpressRouting);

        (new Runtime($app))->boot();
        Cli::register($app);

        return $app;
    }

    private static function storagePath(): string
    {
        return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/cache/meros' : sys_get_temp_dir() . '/meros';
    }
}
