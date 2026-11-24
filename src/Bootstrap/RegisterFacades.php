<?php

namespace MM\Meros\Foundations\Bootstrap;

use Illuminate\Support\Facades\Facade;
use MM\Meros\Foundations\Application;

class RegisterFacades
{
    public function bootstrap(Application $app): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }
}
