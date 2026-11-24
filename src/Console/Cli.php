<?php

namespace MM\Meros\Foundations\Console;

use MM\Meros\Foundations\Application;

/** Registers the `wp meros:*` housekeeping commands. */
final class Cli
{
    public static function register(Application $app): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        foreach ([
            'meros:cache'    => Commands\CacheCommand::class,
            'meros:views'    => Commands\ViewsCommand::class,
            'meros:config'   => Commands\ConfigCommand::class,
            'meros:optimize' => Commands\OptimizeCommand::class,
            'meros:key'      => Commands\KeyCommand::class,
            'meros:publish'  => Commands\PublishCommand::class,
            'meros:queue'    => Commands\QueueCommand::class,
        ] as $name => $class) {
            \WP_CLI::add_command($name, new $class($app));
        }
    }
}
