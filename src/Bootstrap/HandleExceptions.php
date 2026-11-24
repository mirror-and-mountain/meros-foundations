<?php

namespace MM\Foundations\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions as BaseHandleExceptions;

/**
 * Laravel's handler turns every PHP notice into an exception, which WordPress
 * core and plugins trip constantly. Only take over when debugging is on and
 * no fatal-error-handler drop-in is installed.
 */
class HandleExceptions extends BaseHandleExceptions
{
    public function bootstrap(Application $app)
    {
        self::$reservedMemory = str_repeat('x', 32768);
        static::$app          = $app;

        $dropIn = !$app->runningInConsole() && defined('WP_CONTENT_DIR') && is_readable(WP_CONTENT_DIR . '/fatal-error-handler.php');

        if (!$app['config']->get('app.debug', false) || $dropIn) {
            return;
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
}
