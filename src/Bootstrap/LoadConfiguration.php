<?php

namespace MM\Meros\Foundations\Bootstrap;

use Illuminate\Config\Repository;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Config\Stubs;
use Symfony\Component\Finder\Finder;

/**
 * Layers the registered config stubs (Foundations first, later directories
 * winning) and then the application's own `config/` directory, which is
 * applied last. A cached config file short-circuits all of it.
 */
class LoadConfiguration
{
    /** Options that are merged key-by-key rather than replaced. */
    protected const MERGEABLE = [
        'auth'        => ['guards', 'providers', 'passwords'],
        'cache'       => ['stores'],
        'database'    => ['connections'],
        'filesystems' => ['disks'],
        'logging'     => ['channels'],
    ];

    public function bootstrap(Application $app): void
    {
        $cached = is_file($path = $app->getCachedConfigPath());

        $app->instance('config', $config = new Repository($cached ? require $path : static::items($app)));
        $app->instance('config_loaded_from_cache', $cached);

        date_default_timezone_set($config->get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');
    }

    /** Resolve the full, uncached configuration array. */
    public static function items(Application $app): array
    {
        $items = [];

        foreach (Stubs::paths() as $directory) {
            foreach (Stubs::read($directory) as $name => $stub) {
                $items[$name] = static::overlay($name, $items[$name] ?? [], $stub);
            }
        }

        if (is_dir($dir = $app->configPath())) {
            foreach (Finder::create()->files()->name('*.php')->in($dir)->sortByName() as $file) {
                $name         = basename($file->getFilename(), '.php');
                $items[$name] = static::overlay($name, $items[$name] ?? [], (fn () => require $file->getRealPath())());
            }
        }

        return $items;
    }

    protected static function overlay(string $name, array $base, array $over): array
    {
        $merged = array_merge($base, $over);

        foreach (self::MERGEABLE[$name] ?? [] as $option) {
            if (isset($base[$option], $over[$option])) {
                $merged[$option] = array_merge($base[$option], $over[$option]);
            }
        }

        return $merged;
    }
}
