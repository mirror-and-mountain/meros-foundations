<?php

class WP_CLI
{
    public static array $messages = [];
    public static array $commands = [];

    public static function success($message): void { self::$messages[] = $message; }
    public static function warning($message): void { self::$messages[] = $message; }
    public static function log($message): void { self::$messages[] = $message; }
    public static function line($message, $newline = true): void { self::$messages[] = $message; }
    public static function add_command($name, $command): void { self::$commands[$name] = $command; }
    public static function error($message): never { throw new RuntimeException($message); }
    public static function halt($status): never { throw new RuntimeException('Exit status: ' . $status); }
}
