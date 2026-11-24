<?php

namespace MM\Foundations\Bootstrap;

use Illuminate\Support\Facades\Facade;
use MM\Foundations\Application;

class RegisterFacades
{
    public function bootstrap(Application $app): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }
}
