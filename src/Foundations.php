<?php

namespace MM\Foundations;

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use MM\Foundations\Config\Stubs;
use MM\Foundations\Console\Kernel as ConsoleKernel;
use MM\Foundations\Http\Kernel as HttpKernel;
use MM\Foundations\WordPress\Runtime;

final class Foundations
{
    /**
     * Build the application without attaching it to WordPress.
     *
     * @param string[] $providers
     * @param string[] $stubs      extra config stub directories (later wins)
     */
    public static function create(string $root, array $providers = [], array $stubs = [], bool $wordpressRouting = true): Application
    {
        foreach ($stubs as $directory) {
            Stubs::register($directory);
        }

        $app = Application::configure($root)
            ->withProviders($providers)
            ->withRouting(web: is_file($web = "{$root}/routes/web.php") ? $web : null)
            ->withMiddleware(function (Middleware $middleware) {
                // WordPress owns cookies, sessions and nonces on its own pages.
                $middleware->group('wordpress', [SubstituteBindings::class]);
            })
            ->withExceptions()
            ->create();

        $app->useStoragePath(self::storagePath());
        $app->useBootstrapPath($app->storagePath('framework'));

        foreach (['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app'] as $directory) {
            is_dir($path = $app->storagePath($directory)) || @mkdir($path, 0775, true);
        }

        $app->singleton(HttpKernelContract::class, HttpKernel::class);
        $app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);

        $wordpressRouting && $app->handleWordPressRequests();

        return $app;
    }

    /** Create the application and attach it to the current WordPress request or WP-CLI process. */
    public static function boot(string $root, array $providers = [], array $stubs = [], bool $wordpressRouting = true): Application
    {
        $app = self::create($root, $providers, $stubs, $wordpressRouting);

        (new Runtime($app))->boot();

        return $app;
    }

    private static function storagePath(): string
    {
        return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/cache/meros' : sys_get_temp_dir() . '/meros';
    }
}
