<?php

namespace MM\Meros\Foundations\WordPress;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use MM\Meros\Foundations\Application;
use Throwable;

/** Attaches a Foundations application to WordPress (web requests and WP-CLI). */
class Runtime
{
    public function __construct(protected Application $app)
    {
    }

    public function boot(): void
    {
        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));

        $this->app->runningInConsole() ? $this->bootConsole() : $this->bootHttp();
    }

    protected function bootConsole(): void
    {
        $this->app->make(HttpKernel::class)->bootstrap();
    }

    protected function bootHttp(): void
    {
        $kernel  = $this->app->make(HttpKernel::class);
        $request = $this->captureRequest();

        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        $kernel->bootstrap($request);

        URL::useOrigin(apply_filters('meros/root_url', home_url(), $request));

        if ($this->app->handlesWordPressRequests()) {
            $this->registerWordPressRoute(ob_get_level());
        }

        try {
            $route = $this->app->make('router')->getRoutes()->match($request);
        } catch (Throwable) {
            return;
        }

        $this->registerRequestHandler($request, $route);
    }

    /** WordPress slashes superglobals; Laravel must see the raw values. */
    protected function captureRequest(): Request
    {
        $original = [$_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST];

        $_GET     = stripslashes_deep($_GET);
        $_POST    = stripslashes_deep($_POST);
        $_COOKIE  = stripslashes_deep($_COOKIE);
        $_SERVER  = stripslashes_deep($_SERVER);
        $_REQUEST = array_merge($_GET, $_POST);

        try {
            return Request::capture();
        } finally {
            [$_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST] = $original;
        }
    }

    /** Catch-all route that wraps whatever WordPress renders in a Laravel response. */
    protected function registerWordPressRoute(int $obLevel): void
    {
        Route::any('{any?}', fn () => tap(response(''), function (Response $response) use ($obLevel) {
            foreach (headers_list() as $header) {
                [$name, $value] = preg_split('/:\s?/', $header, 2);

                headers_sent() || header_remove($name);
                $response->header($name, $value, $name !== 'Set-Cookie');
            }

            $response->setStatusCode(http_response_code());

            $content = '';

            for ($i = $obLevel, $levels = ob_get_level(); $i < $levels; $i++) {
                $content .= ob_get_clean();
            }

            $response->setContent($content);
        }))->middleware('wordpress')->where('any', '.*')->name('wordpress');
    }

    protected function registerRequestHandler(Request $request, LaravelRoute $route): void
    {
        $path = Str::finish($request->getBaseUrl(), $request->getPathInfo());

        $except = collect([admin_url(), wp_login_url(), wp_registration_url(), site_url('wp-activate.php'), site_url('wp-signup.php'), rest_url()])
            ->map(fn ($url) => parse_url($url, PHP_URL_PATH))
            ->filter()
            ->unique()
            ->all();

        if (Str::startsWith($path, $except) || Str::endsWith($path, '.php')) {
            return;
        }

        add_filter('do_parse_request', fn ($condition, $wp, $params) => apply_filters('meros/router/do_parse_request', $condition, $wp, $params), 100, 3);

        if ($route->getName() !== 'wordpress') {
            add_action('parse_request', fn () => $this->handleRequest($request));

            return;
        }

        if (!$this->app->handlesWordPressRequests() || redirect_canonical(null, false)) {
            return;
        }

        $this->deferToWordPress($request);
    }

    /** Let WordPress render, then emit the Laravel response (and its middleware effects) on shutdown. */
    protected function deferToWordPress(Request $request): void
    {
        ob_start();

        remove_action('shutdown', 'wp_ob_end_flush_all', 1);

        $kernel   = $this->app->make(HttpKernel::class);
        $response = $kernel->handle($request);

        $response->headers->remove('cache-control');

        add_action('send_headers', fn () => $response->setStatusCode(http_response_code())->sendHeaders(), 100);

        add_action('shutdown', function () use ($kernel, $request, $response) {
            $response->sendContent();

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            } elseif (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                Response::closeOutputBuffers(0, true);
                flush();
            }

            $kernel->terminate($request, $response);

            exit((int) $response->isServerError());
        }, 100);
    }

    public function handleRequest(Request $request): void
    {
        $kernel   = $this->app->make(HttpKernel::class);
        $response = $kernel->handle($request);

        $response->send();
        $kernel->terminate($request, $response);

        exit((int) $response->isServerError());
    }
}
