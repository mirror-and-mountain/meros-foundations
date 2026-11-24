<?php

namespace MM\Meros\Foundations\Console\Commands;

use MM\Meros\Foundations\Config\Stubs;

/** Copy Meros config stubs into the active theme's config directory. */
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
     * [--force]
     * : Overwrite existing files.
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc)
    {
        $available = array_keys(Stubs::files());

        if (!empty($assoc['list'])) {
            \WP_CLI::line(implode(PHP_EOL, $available));

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
}
