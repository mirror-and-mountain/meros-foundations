<?php

namespace MM\Meros\Foundations\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\Config\Stubs;

/**
 * Copy Meros config stubs, or the config files a Laravel package declares with
 * `publishes()`, into the active theme's config directory.
 */
class PublishCommand extends Command
{
    /**
     * Publish Meros config files to the theme.
     *
     * ## OPTIONS
     *
     * [<name>...]
     * : Config names to publish (e.g. view cache). Defaults to all.
     *
     * [--list]
     * : List the publishable configs and exit.
     *
     * [--provider=<class>]
     * : Publish the config files declared by a Laravel package's service
     * provider (e.g. Laravel\\Socialite\\SocialiteServiceProvider). Only files
     * destined for the theme's config directory are copied.
     *
     * [--all]
     * : Publish the config files of every registered provider that declares any
     * (Meros packages and Laravel packages alike).
     *
     * [--tag=<tag>]
     * : Limit --provider to a publish tag (e.g. config).
     *
     * [--force]
     * : Overwrite existing files.
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc)
    {
        if (!empty($assoc['all'])) {
            foreach (ServiceProvider::publishableProviders() as $provider) {
                $this->publishProvider($provider, $assoc['tag'] ?? null, !empty($assoc['force']), true);
            }

            return;
        }

        if (!empty($assoc['provider'])) {
            $this->publishProvider($assoc['provider'], $assoc['tag'] ?? null, !empty($assoc['force']));

            return;
        }

        $available = array_keys(Stubs::files());

        if (!empty($assoc['list'])) {
            \WP_CLI::line(implode(PHP_EOL, $available));

            foreach (ServiceProvider::publishableProviders() as $provider) {
                \WP_CLI::line("provider: {$provider}");
            }

            return;
        }

        if ($unknown = array_diff($args, $available)) {
            \WP_CLI::error('Unknown config: ' . implode(', ', $unknown) . '. Available: ' . implode(', ', $available));
        }

        $written = Stubs::publish($this->app->configPath(), $args, !empty($assoc['force']));

        $written
            ? \WP_CLI::success('Published: ' . implode(', ', $written))
            : \WP_CLI::warning('Nothing to publish; files already exist (use --force to overwrite).');
    }

    private function publishProvider(string $provider, ?string $tag, bool $force, bool $quiet = false): void
    {
        $provider = ltrim($provider, '\\');

        if (!$this->app->getProviders($provider)) {
            \WP_CLI::error("{$provider} is not a registered service provider.");
        }

        $config = realpath($this->app->configPath()) ?: rtrim($this->app->configPath(), '/\\');
        $paths = ServiceProvider::pathsToPublish($provider, $tag);
        $written = [];

        foreach ($paths as $from => $to) {
            if (!str_starts_with(rtrim($to, '/\\'), $config . DIRECTORY_SEPARATOR) || str_contains($to, '..')) {
                continue;
            }

            if (is_dir($from)) {
                File::ensureDirectoryExists($to);
                foreach (File::allFiles($from) as $file) {
                    $this->copy($file->getPathname(), $to . '/' . $file->getRelativePathname(), $force, $written);
                }
            } else {
                $this->copy($from, $to, $force, $written);
            }
        }

        if (!$paths) {
            $quiet or \WP_CLI::error("{$provider} declares nothing to publish" . ($tag ? " for tag '{$tag}'." : '.'));
        }

        if ($quiet && !$written) {
            return;
        }

        $written
            ? \WP_CLI::success("{$provider}: " . implode(', ', $written))
            : \WP_CLI::warning('Nothing published; files already exist (use --force) or are outside the config directory.');
    }

    private function copy(string $from, string $to, bool $force, array &$written): void
    {
        if (!$force && file_exists($to)) {
            return;
        }

        File::ensureDirectoryExists(dirname($to));
        File::copy($from, $to);
        $written[] = basename($to);
    }
}
