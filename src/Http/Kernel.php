<?php

namespace MM\Foundations\Http;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use MM\Foundations\Application;
use MM\Foundations\Bootstrap;
use Throwable;

class Kernel implements KernelContract
{
    protected array $bootstrappers = [
        Bootstrap\LoadEnvironmentVariables::class,
        Bootstrap\LoadConfiguration::class,
        Bootstrap\HandleExceptions::class,
        Bootstrap\RegisterFacades::class,
        Bootstrap\RegisterProviders::class,
        Bootstrap\BootProviders::class,
    ];

    public function __construct(protected Application $app, protected Router $router)
    {
        $router->middlewareGroup('web', $this->webMiddleware());
        $router->middlewareGroup('wordpress', $this->webMiddleware());
        $router->middlewareGroup('api', []);
    }

    /** Mirrors Acorn's `web` group: cookies and sessions, no CSRF check. */
    protected function webMiddleware(): array
    {
        return [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \MM\Foundations\Session\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ];
    }

    public function bootstrap()
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);
        }
    }

    public function handle($request)
    {
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        try {
            $this->bootstrap();

            return $this->router->dispatch($request);
        } catch (Throwable $e) {
            $handler = $this->app->make(ExceptionHandler::class);
            $handler->report($e);

            return $handler->render($request, $e);
        }
    }

    public function terminate($request, $response)
    {
        $this->app->terminate();
    }

    public function getApplication()
    {
        return $this->app;
    }
}
