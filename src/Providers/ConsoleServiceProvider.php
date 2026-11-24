<?php

namespace MM\Meros\Foundations\Providers;

use Illuminate\Queue\Console;
use Illuminate\Support\ServiceProvider;

/** Registers the queue commands, which `Artisan::call('queue:work')` and `wp meros:queue` run. */
class ConsoleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Console\WorkCommand::class, fn ($app) => new Console\WorkCommand($app['queue.worker'], $app['cache.store']));
        $this->app->singleton(Console\RestartCommand::class, fn ($app) => new Console\RestartCommand($app['cache.store']));
    }

    public function boot()
    {
        $this->commands([
            Console\WorkCommand::class,
            Console\RestartCommand::class,
            Console\RetryCommand::class,
            Console\ListFailedCommand::class,
            Console\FlushFailedCommand::class,
            Console\ForgetFailedCommand::class,
            Console\ClearCommand::class,
        ]);
    }
}
