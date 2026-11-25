<?php

namespace MM\Meros\Foundations\Testing;

use Composer\Autoload\ClassLoader;
use RuntimeException;

/**
 * Loads repository test namespaces into a standalone or shared Composer autoloader.
 */
final class Bootstrap
{
    /** Load test namespaces for standalone installs or a shared Composer test runner. */
    public static function load(string $root): void
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader && is_file($root . '/vendor/autoload.php')) {
            $loader = require $root . '/vendor/autoload.php';
        }
        if (!$loader instanceof ClassLoader) {
            throw new RuntimeException('Install repository development dependencies or use the shared workspace test runner.');
        }
        $composer = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($composer['autoload-dev']['psr-4'] ?? [] as $namespace => $paths) {
            $loader->addPsr4($namespace, array_map(fn ($path) => $root . '/' . $path, (array) $paths));
        }
    }
}
