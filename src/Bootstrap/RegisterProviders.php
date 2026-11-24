<?php

namespace MM\Meros\Foundations\Bootstrap;

use MM\Meros\Foundations\Application;

class RegisterProviders
{
    public function bootstrap(Application $app): void
    {
        $app->registerConfiguredProviders();
    }
}
