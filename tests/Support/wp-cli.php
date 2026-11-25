<?php

class WP_CLI
{
    public static array $messages = [];
    public static array $commands = [];
    public static array $descriptions = [];

    public static function success($message): void { self::$messages[] = $message; }
    public static function warning($message): void { self::$messages[] = $message; }
    public static function log($message): void { self::$messages[] = $message; }
    public static function line($message, $newline = true): void { self::$messages[] = $message; }
    public static function add_command($name, $command, $options = []): void
    {
        self::$commands[$name] = $command;
        self::$descriptions[$name] = $options['shortdesc'] ?? '';
    }
    public static function get_root_command(): object
    {
        return new class {
            public function get_subcommands(): array
            {
                $commands = [];
                foreach (WP_CLI::$commands as $name => $command) {
                    $commands[$name] = new class($name, WP_CLI::$descriptions[$name]) {
                        public function __construct(private string $name, private string $description) {}
                        public function get_name(): string { return $this->name; }
                        public function get_shortdesc(): string { return $this->description; }
                    };
                }
                return $commands;
            }
        };
    }
    public static function error($message): never { throw new RuntimeException($message); }
    public static function halt($status): never { throw new RuntimeException('Exit status: ' . $status); }
}
