<?php

namespace MM\Meros\Foundations\Console\Commands;

use MM\Meros\Foundations\Application;

abstract class Command
{
    public function __construct(protected Application $app)
    {
    }
}
