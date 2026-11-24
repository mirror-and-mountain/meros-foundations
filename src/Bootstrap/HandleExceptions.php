<?php

namespace MM\Foundations\Bootstrap;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use MM\Foundations\Application;
use Throwable;

/**
 * WordPress core and plugins raise plenty of notices, so PHP errors are only
 * promoted to exceptions when debugging is on and no fatal-error-handler
 * drop-in is installed. Deprecations are never promoted.
 */
class HandleExceptions
{
    protected Application $app;

    public function bootstrap(Application $app): void
    {
        $this->app = $app;

        $dropIn = !$app->runningInConsole() && defined('WP_CONTENT_DIR') && is_readable(WP_CONTENT_DIR . '/fatal-error-handler.php');

        if (!$app->hasDebugModeEnabled() || $dropIn) {
            return;
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function handleError($level, $message, $file = '', $line = 0): bool
    {
        if (!(error_reporting() & $level) || in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($e);

        $this->app->runningInConsole()
            ? $handler->renderForConsole(new \Symfony\Component\Console\Output\ConsoleOutput(), $e)
            : $handler->render($this->app['request'], $e)->send();
    }
}
