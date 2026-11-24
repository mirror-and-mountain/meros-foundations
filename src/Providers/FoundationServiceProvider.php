<?php

namespace MM\Foundations\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (is_file($routes = $this->app->basePath('routes/web.php'))) {
            Route::middleware('web')->group($routes);
        }
    }
}
