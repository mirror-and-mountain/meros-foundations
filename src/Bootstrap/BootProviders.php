<?php

namespace MM\Foundations\Bootstrap;

use MM\Foundations\Application;

class BootProviders
{
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}
