<?php

namespace MM\Meros\Foundations\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use MM\Meros\Foundations\Application;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler implements ExceptionHandler
{
    public function __construct(protected Application $app)
    {
    }

    public function report(Throwable $e)
    {
        if ($this->shouldReport($e)) {
            $this->app['log']->error($e->getMessage(), ['exception' => $e]);
        }
    }

    public function shouldReport(Throwable $e)
    {
        return !$e instanceof HttpExceptionInterface;
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        }

        $status  = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
        $debug   = $this->app->hasDebugModeEnabled();

        $message = $debug || $status < 500 ? $e->getMessage() : 'Server Error';

        if ($request->expectsJson()) {
            return new JsonResponse(
                ['message' => $message] + ($debug ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()] : []),
                $status,
                $headers,
            );
        }

        $body = '<h1>' . $status . '</h1><p>' . e($message) . '</p>' . ($debug ? '<pre>' . e($e->getTraceAsString()) . '</pre>' : '');

        return new Response($body, $status, $headers);
    }

    public function renderForConsole($output, Throwable $e)
    {
        $output->writeln('<error>' . $e::class . ': ' . $e->getMessage() . '</error>');
    }
}
