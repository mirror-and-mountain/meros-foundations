<?php

namespace MM\Foundations\Config;

use Illuminate\Support\Facades\File;

/**
 * Ordered registry of directories holding config stubs (`name.php`).
 *
 * Later registrations override earlier ones, with the same merge rules as an application config file, so a framework can
 * ship stubs on top of Foundations' and an application can still override
 * those by dropping a file into its own config directory.
 */
final class Stubs
{
    /** @var string[] */
    private static array $paths = [];

    public static function register(string $directory): void
    {
        $directory = rtrim($directory, '/\\');

        if (!in_array($directory, self::$paths, true)) {
            self::$paths[] = $directory;
        }
    }

    public static function paths(): array
    {
        return [dirname(__DIR__, 2) . '/config', ...self::$paths];
    }

    public static function flush(): void
    {
        self::$paths = [];
    }

    /** @return array<string,string> config name => absolute file of the winning stub */
    public static function files(): array
    {
        $files = [];

        foreach (self::paths() as $path) {
            foreach (glob($path . '/*.php') ?: [] as $file) {
                $files[basename($file, '.php')] = $file;
            }
        }

        return $files;
    }

    /** @return array<string,array> the configs defined by a single stub directory */
    public static function read(string $directory): array
    {
        $config = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $config[basename($file, '.php')] = (fn () => require $file)();
        }

        return $config;
    }

    /**
     * Copy the winning stubs into a config directory.
     *
     * @return string[] names that were written
     */
    public static function publish(string $target, array $only = [], bool $force = false): array
    {
        File::ensureDirectoryExists($target);

        $written = [];

        foreach (self::files() as $name => $file) {
            if ($only && !in_array($name, $only, true)) {
                continue;
            }

            if (!$force && file_exists("{$target}/{$name}.php")) {
                continue;
            }

            File::copy($file, "{$target}/{$name}.php");
            $written[] = $name;
        }

        return $written;
    }
}
