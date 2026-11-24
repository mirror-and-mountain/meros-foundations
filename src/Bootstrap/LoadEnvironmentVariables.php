<?php

namespace MM\Foundations\Bootstrap;

use Dotenv\Dotenv;
use Illuminate\Support\Env;
use MM\Foundations\Application;

class LoadEnvironmentVariables
{
    public function bootstrap(Application $app): void
    {
        if ($app->configurationIsCached() || !is_file($app->environmentPath() . '/' . $app->environmentFile())) {
            return;
        }

        Dotenv::create(Env::getRepository(), $app->environmentPath(), $app->environmentFile())->safeLoad();
    }
}
