<?php

namespace MM\Foundations\Console;

use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Console\Kernel as BaseKernel;
use MM\Foundations\Bootstrap\HandleExceptions;
use MM\Foundations\Bootstrap\LoadConfiguration;

class Kernel extends BaseKernel
{
    protected $bootstrappers = [
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterFacades::class,
        RegisterProviders::class,
        BootProviders::class,
    ];
}
