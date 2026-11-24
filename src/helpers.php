<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

if (!function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        return $abstract === null ? Container::getInstance() : Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        if ($key === null) {
            return app('config');
        }

        return is_array($key) ? app('config')->set($key) : app('config')->get($key, $default);
    }
}

foreach (['base' => 'basePath', 'config' => 'configPath', 'database' => 'databasePath', 'public' => 'publicPath', 'resource' => 'resourcePath', 'storage' => 'storagePath', 'lang' => 'langPath', 'app' => 'path'] as $name => $method) {
    if (!function_exists("{$name}_path")) {
        eval("function {$name}_path(\$path = '') { return app()->{$method}(\$path); }");
    }
}

if (!function_exists('request')) {
    function request($key = null, $default = null)
    {
        $request = app('request');

        return $key === null ? $request : $request->input($key, $default);
    }
}

if (!function_exists('response')) {
    function response($content = '', $status = 200, array $headers = [])
    {
        $factory = app(ResponseFactory::class);

        return func_num_args() === 0 ? $factory : $factory->make($content, $status, $headers);
    }
}

if (!function_exists('view')) {
    function view($view = null, $data = [], $mergeData = [])
    {
        $factory = app('view');

        return func_num_args() === 0 ? $factory : $factory->make($view, $data, $mergeData);
    }
}

if (!function_exists('url')) {
    function url($path = null, $parameters = [], $secure = null)
    {
        return $path === null ? app('url') : app('url')->to($path, $parameters, $secure);
    }
}

if (!function_exists('route')) {
    function route($name, $parameters = [], $absolute = true)
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('redirect')) {
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        return $to === null ? app('redirect') : app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('session')) {
    function session($key = null, $default = null)
    {
        if ($key === null) {
            return app('session');
        }

        return is_array($key) ? app('session')->put($key) : app('session')->get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return app()->has('session') ? app('session')->token() : throw new RuntimeException('Application session store not set.');
    }
}

if (!function_exists('logger')) {
    function logger($message = null, array $context = [])
    {
        return $message === null ? app('log') : app('log')->debug($message, $context);
    }
}

if (!function_exists('report')) {
    function report($exception)
    {
        app(ExceptionHandler::class)->report(is_string($exception) ? new Exception($exception) : $exception);
    }
}

if (!function_exists('abort')) {
    function abort($code, $message = '', array $headers = [])
    {
        throw $code === 404 ? new NotFoundHttpException($message, null, 0, $headers) : new HttpException($code, $message, null, $headers);
    }
}

if (!function_exists('event')) {
    function event(...$args)
    {
        return app('events')->dispatch(...$args);
    }
}
