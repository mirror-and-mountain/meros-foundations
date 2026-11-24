<?php

namespace MM\Foundations;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as BaseApplication;
use Illuminate\Foundation\PackageManifest as BasePackageManifest;

class Application extends BaseApplication
{
    /** Whether unmatched requests are handed to WordPress through a catch-all route. */
    protected bool $handlesWordPress = false;

    public function handleWordPressRequests(): static
    {
        $this->handlesWordPress = true;

        return $this;
    }

    public function handlesWordPressRequests(): bool
    {
        return $this->handlesWordPress;
    }

    /** Resolve `.env` from the closest parent directory that has one. */
    public function environmentPath()
    {
        if ($this->environmentPath) {
            return $this->environmentPath;
        }

        for ($dir = $this->basePath(); dirname($dir) !== $dir; $dir = dirname($dir)) {
            if (is_file("{$dir}/.env")) {
                return $dir;
            }
        }

        return $this->basePath();
    }

    public function isDownForMaintenance()
    {
        return is_file($this->storagePath('framework/down'))
            || defined('ABSPATH') && is_file(ABSPATH . '.maintenance');
    }

    protected function registerBaseBindings()
    {
        parent::registerBaseBindings();

        $this->singleton(BasePackageManifest::class, function () {
            $paths = [$this->basePath(), get_template_directory(), get_stylesheet_directory()];

            if (defined('WP_CONTENT_DIR') && is_file(WP_CONTENT_DIR . '/composer.json')) {
                $paths[] = WP_CONTENT_DIR;
            }

            $paths = apply_filters('meros/composer_paths', $paths);
            $paths = array_filter(array_unique(array_map(fn ($p) => rtrim($p, '/\\'), $paths)), fn ($p) => is_file("{$p}/composer.json"));

            return new PackageManifest(new Filesystem(), $paths, $this->getCachedPackagesPath());
        });
    }

    public function version()
    {
        return 'Meros Foundations (Laravel ' . parent::VERSION . ')';
    }
}
