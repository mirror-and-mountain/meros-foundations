<?php

namespace MM\Foundations\Bootstrap;

use Illuminate\Foundation\Bootstrap\LoadConfiguration as BaseLoadConfiguration;
use MM\Foundations\Config\Stubs;

/**
 * Layers Laravel's framework defaults, then every registered stub directory;
 * the application's own config directory is applied last by the parent class.
 */
class LoadConfiguration extends BaseLoadConfiguration
{
    protected function getBaseConfiguration()
    {
        $config = parent::getBaseConfiguration();

        foreach (Stubs::paths() as $directory) {
            foreach (Stubs::read($directory) as $name => $stub) {
                $config[$name] = $this->overlay($name, $config[$name] ?? [], $stub);
            }
        }

        return $config;
    }

    protected function overlay(string $name, array $base, array $over): array
    {
        $merged = array_merge($base, $over);

        foreach ($this->mergeableOptions($name) as $option) {
            if (isset($base[$option], $over[$option])) {
                $merged[$option] = array_merge($base[$option], $over[$option]);
            }
        }

        return $merged;
    }
}
