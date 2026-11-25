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
use RuntimeException;

/**
 * Boots Foundations in real WordPress tests and cleans up fixture files, tracked tables and registered blocks.
 */
abstract class WordPressTestCase extends \WP_UnitTestCase
{
    protected Application $app;
    protected string $directory;
    protected Filesystem $files;
    protected array $tablesToDrop = [];
    private array $registeredBlocks = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->registeredBlocks = array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered());
        $this->files = new Filesystem();
        $temporary = realpath(sys_get_temp_dir()) ?: throw new RuntimeException('Temporary directory unavailable.');
        $this->directory = $temporary . '/meros-wp-test-' . bin2hex(random_bytes(8));
        $this->files->ensureDirectoryExists($this->directory . '/storage');
        $this->files->put($this->directory . '/composer.json', '{"name":"meros/test-theme"}');
        $this->files->put($this->directory . '/style.css', "/* Theme Name: Meros Test Theme */\n");
        foreach (['stylesheet_directory', 'template_directory'] as $hook) {
            add_filter($hook, fn () => $this->directory);
        }
        Stubs::flush();
        ServiceProvider::$publishes = ServiceProvider::$publishGroups = [];
        $this->app = $this->createApplication();
    }

    protected function createApplication(array $providers = []): Application
    {
        $this->files->ensureDirectoryExists($this->directory . '/config');
        $this->files->put($this->directory . '/config/app.php', '<?php return ' . var_export([
            'key' => 'base64:' . base64_encode(str_repeat('t', 32)),
        ], true) . ';');
        $host = wp_parse_url('mysql://' . DB_HOST);
        if (!is_array($host) || empty($host['host'])) {
            throw new RuntimeException('MEROS_TEST_DB_HOST must be a TCP hostname, optionally followed by a port.');
        }
        $this->files->put($this->directory . '/config/database.php', '<?php return ' . var_export([
            'default' => 'wordpress',
            'connections' => ['wordpress' => array_replace(
                (require dirname(__DIR__, 2) . '/config/database.php')['connections']['wordpress'],
                [
                    'host' => $host['host'],
                    'port' => $host['port'] ?? 3306,
                ]
            )],
        ], true) . ';');
        $app = $this->app = Foundations::create($this->directory, $providers, wordpressRouting: false);
        $app->usePaths(['environment' => $this->directory]);
        $app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();
        return $app;
    }

    /** Register unprefixed names of custom tables this test creates outside WP's transaction. */
    protected function trackTable(string $name): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException('Test table names must contain only lowercase letters, digits and underscores.');
        }
        $this->tablesToDrop[] = $name;
    }

    public function tear_down(): void
    {
        try {
            $registry = \WP_Block_Type_Registry::get_instance();
            foreach (array_diff(array_keys($registry->get_all_registered()), $this->registeredBlocks) as $block) {
                $registry->unregister($block);
            }
            if (isset($this->app)) {
                $schema = $this->app['db']->connection()->getSchemaBuilder();
                if ($this->app['db']->connection()->getTablePrefix() !== 'wptests_') {
                    throw new RuntimeException('Refusing cleanup outside the WordPress test table prefix.');
                }
                $schema->disableForeignKeyConstraints();
                try {
                    foreach (array_reverse(array_unique($this->tablesToDrop)) as $table) {
                        $schema->dropIfExists($table);
                    }
                } finally {
                    $schema->enableForeignKeyConstraints();
                }
                foreach ($this->app['db']->getConnections() as $connection) {
                    $connection->disconnect();
                }
                $this->app->flush();
            }
            Model::unsetConnectionResolver();
            Model::unsetEventDispatcher();
            Container::setInstance(null);
            Facade::setFacadeApplication(null);
            Stubs::flush();
            ServiceProvider::$publishes = ServiceProvider::$publishGroups = [];
            if (isset($this->directory)) {
                $this->files->deleteDirectory($this->directory);
            }
        } finally {
            parent::tear_down();
        }
    }
}
