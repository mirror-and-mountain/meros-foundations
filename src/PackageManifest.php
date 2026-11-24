<?php

namespace MM\Meros\Foundations;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Package discovery across several composer roots (theme, content root, ...)
 * rather than only the application's own vendor directory.
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

    /** @return string[] */
    public function providers(): array
    {
        return array_values(array_unique(array_merge(...array_column($this->manifest(), 'providers') ?: [[]])));
    }

    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!is_file($this->manifestPath)) {
            $this->build();
        }

        return $this->manifest = is_file($this->manifestPath) ? (require $this->manifestPath) : [];
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
                $extra  = $package['extra']['meros'] ?? $package['extra']['laravel'] ?? [];
                $ignore = array_merge($ignore, $extra['dont-discover'] ?? []);

                if (!empty($extra['providers'])) {
                    $packages[$package['name'] ?? basename($root)] = ['providers' => (array) $extra['providers']];
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
