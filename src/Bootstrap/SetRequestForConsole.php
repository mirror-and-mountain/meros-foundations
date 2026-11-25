<?php

namespace MM\Meros\Foundations\Bootstrap;

use Illuminate\Http\Request;
use MM\Meros\Foundations\Application;

class SetRequestForConsole
{
    public function bootstrap(Application $app): void
    {
        if (!$app->runningInConsole() || $app->bound('request')) {
            return;
        }

        $url = $app['config']->get('app.url', 'http://localhost');
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        $app->instance('request', Request::create($url, server: [
            'SCRIPT_NAME' => $path,
            'SCRIPT_FILENAME' => $path,
        ]));
    }
}
