<?php

namespace MM\Meros\Foundations\Console\Commands;

use MM\Meros\Foundations\Bootstrap\LoadConfiguration;

/** Cache the merged configuration into a single file. */
class ConfigCommand extends Command
{
    /**
     * Cache the merged configuration. Config files must not contain closures.
     *
     * @subcommand cache
     */
    public function cache()
    {
        $this->clear(quiet: true);

        $path = $this->app->getCachedConfigPath();
        $this->app['files']->ensureDirectoryExists(dirname($path));
        $this->app['files']->put($path, '<?php return ' . var_export(LoadConfiguration::items($this->app), true) . ';' . PHP_EOL);

        try {
            require $path;
        } catch (\Throwable $e) {
            $this->app['files']->delete($path);
            \WP_CLI::error('Configuration could not be cached: ' . $e->getMessage());
        }

        \WP_CLI::success('Configuration cached.');
    }

    /**
     * Remove the cached configuration.
     *
     * @subcommand clear
     */
    public function clear($args = [], $assoc = [], bool $quiet = false)
    {
        $this->app['files']->delete($this->app->getCachedConfigPath());

        $quiet || \WP_CLI::success('Configuration cache cleared.');
    }
}
