<?php

namespace MM\Meros\Foundations;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Package discovery across several composer roots (theme, content root, ...).
 *
 * Two kinds of provider are discovered:
 *  - `extra.laravel.providers`: ordinary Laravel packages (e.g. Livewire), registered by Foundations.
 *  - `extra.meros.provider` / `extra.meros.providers`: Meros packages, registered by the framework once its own
 *    provider is up. A package can list `dont-discover` entries under either key; the root's win.
 *
 * The manifest is rebuilt whenever a composer.json or installed.json is newer than the cache, so no
 * composer hook is required.
 */
class PackageManifest
{
    protected ?array $manifest = null;

    /** @param string[] $composerPaths directories holding a composer.json */
    public function __construct(
        protected Filesystem $files,
        public array $composerPaths,
        protected string $manifestPath,
    ) {
    }

    /** @return string[] Laravel package providers */
    public function providers(): array
    {
        return $this->collect('providers');
    }

    /** @return string[] Meros package providers */
    public function merosProviders(): array
    {
        return $this->collect('meros');
    }

    protected function collect(string $key): array
    {
        return array_values(array_unique(array_merge([], ...array_column($this->manifest(), $key))));
    }

    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if ($this->isStale()) {
            $this->build();
        }

        return $this->manifest = is_file($this->manifestPath) ? (require $this->manifestPath) : [];
    }

    protected function isStale(): bool
    {
        if (!is_file($this->manifestPath)) {
            return true;
        }

        $built = filemtime($this->manifestPath);

        foreach ($this->composerPaths as $root) {
            foreach (["{$root}/composer.json", "{$root}/vendor/composer/installed.json"] as $file) {
                if (is_file($file) && filemtime($file) > $built) {
                    return true;
                }
            }
        }

        return false;
    }

    public function build(): void
    {
        $packages = [];
        $ignore   = [];

        foreach ($this->composerPaths as $root) {
            $installed = "{$root}/vendor/composer/installed.json";
            $found     = $this->files->exists($installed) ? (json_decode($this->files->get($installed), true)['packages'] ?? []) : [];
            $found[]   = json_decode($this->files->get("{$root}/composer.json"), true) ?? [];

            foreach ($found as $package) {
                $extra = $package['extra'] ?? [];

                $ignore = array_merge($ignore, $extra['laravel']['dont-discover'] ?? [], $extra['meros']['dont-discover'] ?? []);

                $laravel = (array) ($extra['laravel']['providers'] ?? []);
                $meros   = array_merge((array) ($extra['meros']['providers'] ?? []), (array) ($extra['meros']['provider'] ?? []));

                if ($laravel || $meros) {
                    $packages[$package['name'] ?? basename($root)] = ['providers' => $laravel, 'meros' => $meros];
                }
            }
        }

        $packages = in_array('*', $ignore, true) ? [] : array_diff_key($packages, array_flip($ignore));

        $this->files->ensureDirectoryExists(dirname($this->manifestPath));

        if (!is_writable(dirname($this->manifestPath))) {
            throw new RuntimeException(dirname($this->manifestPath) . ' must be present and writable.');
        }

        $this->files->replace($this->manifestPath, '<?php return ' . var_export($packages, true) . ';' . PHP_EOL);
        $this->manifest = null;
    }

    public function flush(): void
    {
        $this->files->delete($this->manifestPath);
        $this->manifest = null;
    }
}
