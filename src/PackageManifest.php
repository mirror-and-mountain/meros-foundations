<?php

namespace MM\Foundations;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest as BasePackageManifest;
use Illuminate\Support\Collection;

/**
 * Package discovery across several composer roots (theme, content root, ...)
 * rather than only the application's own vendor directory.
 */
class PackageManifest extends BasePackageManifest
{
    /** @param string[] $composerPaths directories holding a composer.json */
    public function __construct(Filesystem $files, public array $composerPaths, $manifestPath)
    {
        $this->files        = $files;
        $this->manifestPath = $manifestPath;
    }

    public function build()
    {
        $packages = [];
        $ignore   = $this->packagesToIgnore();

        foreach ($this->composerPaths as $root) {
            $installed = "{$root}/vendor/composer/installed.json";
            $found     = $this->files->exists($installed)
                ? (json_decode($this->files->get($installed), true)['packages'] ?? [])
                : [];

            $found[] = json_decode($this->files->get("{$root}/composer.json"), true) ?? [];

            foreach ($found as $package) {
                $extra = $package['extra']['meros'] ?? $package['extra']['laravel'] ?? [];

                $ignore = array_merge($ignore, $extra['dont-discover'] ?? []);

                if ($extra) {
                    $packages[$package['name'] ?? basename($root)] = $extra;
                }
            }
        }

        $this->write(
            in_array('*', $ignore, true)
                ? []
                : (new Collection($packages))->reject(fn ($c, $name) => in_array($name, $ignore, true))->all(),
        );
    }

    protected function packagesToIgnore()
    {
        $ignore = [];

        foreach ($this->composerPaths as $root) {
            if ($this->files->exists($file = "{$root}/composer.json")) {
                $extra  = json_decode($this->files->get($file), true)['extra'] ?? [];
                $ignore = array_merge($ignore, $extra['laravel']['dont-discover'] ?? [], $extra['meros']['dont-discover'] ?? []);
            }
        }

        return $ignore;
    }
}
