<?php

namespace MM\Meros\Foundations\Http;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Bootstrap;
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

    /** @var string[] Global middleware, run around every routed request. */
    protected array $middleware = [];

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
            \MM\Meros\Foundations\Session\StartSession::class,
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

            $response = (new Pipeline($this->app))
                ->send($request)
                ->through($this->middleware)
                ->then(fn ($request) => $this->router->dispatch($request));
        } catch (Throwable $e) {
            $handler = $this->app->make(ExceptionHandler::class);
            $handler->report($e);

            $response = $handler->render($request, $e);
        }

        // Livewire and other packages listen for this to post-process the response.
        $this->app['events']->dispatch(new Events\RequestHandled($request, $response));

        return $response;
    }

    public function hasMiddleware($middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    public function pushMiddleware($middleware): static
    {
        if (!$this->hasMiddleware($middleware)) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    public function prependMiddleware($middleware): static
    {
        if (!$this->hasMiddleware($middleware)) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
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
