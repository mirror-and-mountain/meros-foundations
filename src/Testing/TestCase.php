<?php

namespace MM\Meros\Foundations\Testing;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Config\Stubs;
use MM\Meros\Foundations\Foundations;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RuntimeException;

/**
 * Provides isolated Foundations applications with WordPress stubs, disposable files and SQLite defaults.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected ?Application $app = null;
    protected string $directory;
    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        if (defined('ABSPATH') && function_exists('wp_get_theme')) {
            throw new RuntimeException('Use WordPressTestCase for real WordPress tests.');
        }
        require_once __DIR__ . '/wordpress-stubs.php';
        foreach ([
            'ABSPATH' => sys_get_temp_dir() . '/meros-isolated-wordpress/',
            'WP_CONTENT_DIR' => sys_get_temp_dir() . '/meros-isolated-wordpress/wp-content',
            'WP_ENV' => 'testing',
            'DB_HOST' => 'unused',
            'DB_NAME' => 'meros_tests',
            'DB_USER' => 'unused',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
        ] as $name => $value) {
            defined($name) || define($name, $value);
        }
        $this->files = new Filesystem();
        $temporary = realpath(sys_get_temp_dir()) ?: throw new RuntimeException('Temporary directory unavailable.');
        $this->directory = $temporary . '/meros-test-' . bin2hex(random_bytes(8));
        $this->files->ensureDirectoryExists($this->directory);
        WordPressState::reset($this->directory);
        Stubs::flush();
        ServiceProvider::$publishes = ServiceProvider::$publishGroups = [];
        Facade::clearResolvedInstances();
    }

    /** Boot a fresh application with an in-memory database and disposable paths. */
    protected function createApplication(array $providers = [], array $config = [], array $stubs = []): Application
    {
        $defaults = [
            'app' => ['key' => 'base64:' . base64_encode(str_repeat('t', 32))],
            'database' => [
                'default' => 'testing',
                'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
            ],
            'cache' => ['default' => 'array'],
            'session' => ['driver' => 'array'],
        ];
        foreach (array_replace_recursive($defaults, $config) as $name => $values) {
            $this->writeFile("config/{$name}.php", '<?php return ' . var_export($values, true) . ';');
        }
        if (!$this->files->exists($this->directory . '/composer.json')) {
            $this->writeFile('composer.json', '{"name":"meros/test-fixture"}');
        }
        $this->files->ensureDirectoryExists($this->directory . '/storage');
        $this->app = Foundations::create($this->directory, $providers, $stubs, wordpressRouting: false);
        $this->app->usePaths(['environment' => $this->directory]);
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();
        return $this->app;
    }

    protected function writeFile(string $path, string $contents): string
    {
        if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Fixture paths must be relative to the test directory.');
        }
        $target = $this->directory . '/' . $path;
        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->put($target, $contents);
        return $target;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app?->bound('db')) {
                foreach ($this->app['db']->getConnections() as $connection) {
                    $connection->disconnect();
                }
            }
            $this->app?->flush();
            Model::unsetConnectionResolver();
            Model::unsetEventDispatcher();
            Facade::setFacadeApplication(null);
            Container::setInstance(null);
            Stubs::flush();
            ServiceProvider::$publishes = ServiceProvider::$publishGroups = [];
            $this->files->deleteDirectory($this->directory);
        } finally {
            parent::tearDown();
        }
    }
}
