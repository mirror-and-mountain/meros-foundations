<?php

namespace MM\Meros\Foundations\Bootstrap;

use MM\Meros\Foundations\Application;

class BootProviders
{
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}
