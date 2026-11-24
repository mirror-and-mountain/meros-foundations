<?php

namespace MM\Foundations\Bootstrap;

use MM\Foundations\Application;

class RegisterProviders
{
    public function bootstrap(Application $app): void
    {
        $app->registerConfiguredProviders();
    }
}
