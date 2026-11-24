<?php

namespace MM\Meros\Foundations;

use MM\Meros\Foundations\Config\Stubs;
use MM\Meros\Foundations\Console\Cli;
use MM\Meros\Foundations\WordPress\Runtime;

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
            ->usePaths(self::paths($root))
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

    /**
     * Path overrides, as Acorn: MEROS_{TYPE}_PATH env var or constant (relative
     * values resolve from the theme), otherwise a `storage` directory in the
     * theme, otherwise wp-content/cache/meros.
     */
    private static function paths(string $root): array
    {
        $paths = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'storage'] as $type) {
            $key   = 'MEROS_' . strtoupper($type) . '_PATH';
            $value = getenv($key) ?: $_ENV[$key] ?? $_SERVER[$key] ?? (defined($key) ? constant($key) : null);

            if ($value) {
                $paths[$type] = preg_match('#^([/\\\\]|[A-Za-z]:)#', $value) ? $value : "{$root}/{$value}";
            }
        }

        if (!isset($paths['storage'])) {
            $themeStorage = function_exists('get_theme_file_path') ? get_theme_file_path('storage') : null;

            $paths['storage'] = match (true) {
                is_dir("{$root}/storage")                    => "{$root}/storage",
                $themeStorage && is_dir($themeStorage)       => $themeStorage,
                default                                      => self::fallbackStoragePath(),
            };
        }

        return $paths;
    }

    private static function fallbackStoragePath(): string
    {
        return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/cache/meros' : sys_get_temp_dir() . '/meros';
    }
}
