<?php

namespace MM\Meros\Foundations\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->booted(function () {
            // Fluent names and controller actions are assigned after routes enter the collection.
            $routes = $this->app['router']->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });

        if (is_file($routes = $this->app->basePath('routes/web.php'))) {
            Route::middleware('web')->group($routes);
        }
    }
}
