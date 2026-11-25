<?php

namespace MM\Meros\Foundations\Bootstrap;

use Illuminate\Support\Facades\Facade;
use MM\Meros\Foundations\Application;

class RegisterFacades
{
    private static array $aliases = [];
    private static bool $registered = false;

    public function bootstrap(Application $app): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        self::$aliases = array_merge(
            Facade::defaultAliases()->all(),
            $app->make('config')->get('app.aliases', [])
        );

        if (!self::$registered) {
            spl_autoload_register(static function (string $class): void {
                if (isset(self::$aliases[$class])) {
                    class_alias(self::$aliases[$class], $class);
                }
            }, true, true);
            self::$registered = true;
        }
    }
}
