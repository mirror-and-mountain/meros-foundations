<?php

namespace MM\Foundations\Console\Commands;

use MM\Foundations\Application;

abstract class Command
{
    public function __construct(protected Application $app)
    {
    }
}
