<?php

namespace MM\Foundations\Console\Commands;

/** Manage the application cache. */
class CacheCommand extends Command
{
    /**
     * Flush the application cache.
     *
     * ## OPTIONS
     *
     * [--store=<store>]
     * : The cache store to flush. Defaults to the default store.
     *
     * @subcommand clear
     */
    public function clear($args, $assoc)
    {
        $this->app['cache']->store($assoc['store'] ?? null)->flush();

        \WP_CLI::success('Application cache cleared.');
    }

    /**
     * Remove a single item from the cache.
     *
     * ## OPTIONS
     *
     * <key>
     * : The cache key.
     *
     * [--store=<store>]
     * : The cache store.
     */
    public function forget($args, $assoc)
    {
        $this->app['cache']->store($assoc['store'] ?? null)->forget($args[0]);

        \WP_CLI::success("Removed '{$args[0]}' from the cache.");
    }
}
