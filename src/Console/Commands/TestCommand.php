<?php

namespace MM\Meros\Foundations\Console\Commands;

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

/** Discover and run PHPUnit suites in the theme and installed Meros packages. */
class TestCommand extends Command
{
    /**
     * List repositories with structured tests.
     *
     * @subcommand list
     */
    public function packages(): void
    {
        foreach ($this->targets() as $name => $path) {
            \WP_CLI::line($name . '  ' . $path);
        }
    }

    /**
     * Run a repository's PHPUnit suite with named pass/fail results and a summary.
     *
     * ## OPTIONS
     *
     * [<package>]
     * : Full or unique short Composer name. Defaults to the theme or shared workspace suite.
     *
     * [--suite=<suite>]
     * : unit (default) or integration. Integration requires a dedicated WordPress test database.
     *
     * [--filter=<pattern>]
     * : PHPUnit test-name filter.
     *
     * @subcommand run
     */
    public function run(array $args, array $assoc): void
    {
        $suite = $assoc['suite'] ?? 'unit';
        if (!in_array($suite, ['unit', 'integration'], true)) {
            \WP_CLI::error('Suite must be unit or integration.');
        }
        $targets = $this->targets();
        $name = $args[0] ?? null;
        $default = is_file($this->app->basePath('phpunit.xml'))
            ? $this->app->basePath()
            : $this->app->basePath('tools/testing');
        $matches = array_filter($targets, fn ($path, $key) => $name === null
            ? realpath($path) === realpath($default)
            : $key === $name || str_ends_with($key, '/' . $name), ARRAY_FILTER_USE_BOTH);
        if (count($matches) !== 1) {
            \WP_CLI::error('Specify a full or unique testable package name from `wp meros:test list`.');
        }
        $path = reset($matches);
        $config = $path . ($suite === 'unit' ? '/phpunit.xml' : '/phpunit.integration.xml');
        if (!is_file($config)) {
            \WP_CLI::error("No {$suite} configuration exists in {$path}.");
        }
        $shared = $this->app->basePath('tools/testing');
        $runner = $shared . '/vendor/bin/phpunit';
        $usesSharedRunner = is_file($runner);
        if (!$usesSharedRunner) {
            $runner = $path . '/vendor/bin/phpunit';
            if (!is_file($runner)) {
                $runner = $this->app->basePath('vendor/bin/phpunit');
            }
        }
        if (!is_file($runner)) {
            \WP_CLI::error('Install PHPUnit development dependencies in the selected repository, active theme or tools/testing workspace runner.');
        }
        $command = [PHP_BINARY, $runner, '-c', $config, '--testdox'];
        if ($usesSharedRunner) {
            $bootstrap = $shared . ($suite === 'unit' ? '/bootstrap.php' : '/bootstrap-integration.php');
            if (!is_file($bootstrap)) {
                \WP_CLI::error("Shared test bootstrap missing: {$bootstrap}.");
            }
            $command[] = '--bootstrap';
            $command[] = $bootstrap;
        }
        if (isset($assoc['filter'])) {
            $command[] = '--filter';
            $command[] = $assoc['filter'];
        }
        $process = new Process($command, $path);
        $process->setTimeout(null);
        $status = $process->run(function ($type, $output): void {
            echo $output;
        });
        if ($status !== 0) {
            \WP_CLI::halt($status);
        }
        \WP_CLI::success('Tests passed.');
    }

    /** @return array<string, string> Composer names mapped to testable repository roots. */
    protected function targets(): array
    {
        $targets = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $path = InstalledVersions::getInstallPath($name);
            if ($path && is_file($path . '/phpunit.xml')) {
                $targets[$name] = $path;
            }
        }
        foreach ([$this->app->basePath(), $this->app->basePath('tools/testing')] as $path) {
            $composer = $path . '/composer.json';
            if (is_file($composer) && is_file($path . '/phpunit.xml')) {
                $name = json_decode(file_get_contents($composer), true, flags: JSON_THROW_ON_ERROR)['name'] ?? 'theme';
                $targets[$name] = $path;
            }
        }
        ksort($targets);
        return $targets;
    }
}
