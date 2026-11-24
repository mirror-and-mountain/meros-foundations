<?php

namespace MM\Foundations\Console\Commands;

use MM\Foundations\PackageManifest;

/** Cache config, package discovery and views in one go. */
class OptimizeCommand extends Command
{
    /**
     * Cache the configuration, package manifest and compiled views.
     *
     * @subcommand run
     */
    public function run()
    {
        (new ConfigCommand($this->app))->cache();

        $this->app->make(PackageManifest::class)->build();
        \WP_CLI::log('Package manifest cached.');

        (new ViewsCommand($this->app))->cache();
    }

    /**
     * Remove every optimisation cache.
     *
     * @subcommand clear
     */
    public function clear()
    {
        (new ConfigCommand($this->app))->clear(quiet: true);
        $this->app->make(PackageManifest::class)->flush();
        (new ViewsCommand($this->app))->clear();

        \WP_CLI::success('Optimisation caches cleared.');
    }
}
