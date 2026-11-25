<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\Console\Commands\CacheCommand;
use MM\Meros\Foundations\Console\Commands\ConfigCommand;
use MM\Meros\Foundations\Console\Commands\OptimizeCommand;
use MM\Meros\Foundations\Console\Commands\PublishCommand;
use MM\Meros\Foundations\Console\Commands\TestCommand;
use MM\Meros\Foundations\Console\Commands\ViewsCommand;
use MM\Meros\Foundations\PackageManifest;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Tests CLI cache maintenance, publishing and test-runner arguments, failures and streamed output.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ConsoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require __DIR__ . '/../Support/wp-cli.php';
    }

    public function test_housekeeping_commands_clear_real_fixture_caches(): void
    {
        $app = $this->createApplication();
        $app['cache']->put('probe', 'value');
        (new CacheCommand($app))->clear([], []);
        $this->assertFalse($app['cache']->has('probe'));
        $view = $app['config']->get('view.compiled') . '/probe.php';
        $this->files->put($view, 'compiled');
        (new ViewsCommand($app))->clear();
        $this->assertFileDoesNotExist($view);
        (new ConfigCommand($app))->cache();
        $this->assertFileExists($app->getCachedConfigPath());
        (new ConfigCommand($app))->clear();
        $this->assertFileDoesNotExist($app->getCachedConfigPath());
        $app->make(PackageManifest::class)->build();
        (new OptimizeCommand($app))->clear();
        $this->assertFileDoesNotExist($app->getCachedPackagesPath());
    }

    public function test_vendor_publishing_limits_destinations_to_config(): void
    {
        $app = $this->createApplication();
        $this->writeFile('vendor-stub.php', '<?php return ["key" => "value"];');
        $app->register(PublishProvider::class);
        $publish = new PublishCommand($app);
        $publish([], ['provider' => PublishProvider::class, 'tag' => 'config']);
        $this->assertFileExists($app->configPath('vendor-stub.php'));
        $this->assertFileDoesNotExist($app->publicPath('escape.php'));
        $this->files->delete($app->configPath('vendor-stub.php'));
        clearstatcache();
        $publish([], ['all' => true]);
        clearstatcache();
        $this->assertFileExists($app->configPath('vendor-stub.php'));
    }

    public function test_test_command_preserves_filter_arguments_and_failure_status(): void
    {
        $app = $this->createApplication();
        $this->writeFile('phpunit.xml', '<phpunit/>');
        $runner = $this->writeFile('vendor/bin/phpunit', '<?php echo json_encode($argv); exit(3);');
        $command = new class($app) extends TestCommand {
            protected function targets(): array { return ['meros/fixture' => $this->app->basePath()]; }
        };
        ob_start();
        try {
            $command->run(['fixture'], ['filter' => 'a pattern; echo unsafe']);
            $this->fail('The child process failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Exit status: 3', $exception->getMessage());
        } finally {
            $output = ob_get_clean();
        }
        $arguments = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$runner, '-c', $app->basePath('phpunit.xml'), '--testdox', '--filter', 'a pattern; echo unsafe'], $arguments);
        $this->assertNotContains('Tests passed.', \WP_CLI::$messages);
    }

    public function test_test_command_rejects_unknown_suites(): void
    {
        $app = $this->createApplication();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Suite must be unit or integration.');
        (new TestCommand($app))->run([], ['suite' => 'production']);
    }

    public function test_test_command_prefers_the_shared_runner_and_its_bootstrap(): void
    {
        $app = $this->createApplication();
        $this->writeFile('phpunit.xml', '<phpunit/>');
        $this->writeFile('vendor/bin/phpunit', '<?php exit(3);');
        $runner = $this->writeFile('tools/testing/vendor/bin/phpunit', '<?php echo json_encode($argv);');
        $bootstrap = $this->writeFile('tools/testing/bootstrap.php', '<?php');
        $command = new class($app) extends TestCommand {
            protected function targets(): array { return ['meros/fixture' => $this->app->basePath()]; }
        };
        ob_start();
        try {
            $command->run(['fixture'], []);
        } finally {
            $output = ob_get_clean();
        }
        $arguments = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$runner, '-c', $app->basePath('phpunit.xml'), '--testdox', '--bootstrap', $bootstrap], $arguments);
        $this->assertContains('Tests passed.', \WP_CLI::$messages);
    }

    public function test_test_command_streams_results_without_extra_newlines(): void
    {
        $app = $this->createApplication();
        $this->writeFile('phpunit.xml', '<phpunit/>');
        $this->writeFile('vendor/bin/phpunit', <<<'PHP'
<?php
echo "Framework boot\n [x] Uses";
flush();
usleep(100000);
echo " services\n\nOK (1 test, 1 assertion)\n";
PHP);
        $command = new class($app) extends TestCommand {
            protected function targets(): array { return ['meros/fixture' => $this->app->basePath()]; }
        };
        ob_start();
        try {
            $command->run(['fixture'], []);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertSame("Framework boot\n [x] Uses services\n\nOK (1 test, 1 assertion)\n", $output);
        $this->assertContains('Tests passed.', \WP_CLI::$messages);
    }
}

class PublishProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            $this->app->basePath('vendor-stub.php') => $this->app->configPath('vendor-stub.php'),
            $this->app->basePath('vendor-stub.php') . '/unused' => $this->app->publicPath('escape.php'),
        ], 'config');
    }
}
