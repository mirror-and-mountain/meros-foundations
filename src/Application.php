<?php

namespace MM\Meros\Foundations;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Log\Context\ContextServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * A lean application container for running Laravel components inside WordPress.
 * Service providers are always registered eagerly (no deferred loading).
 */
class Application extends Container implements ApplicationContract
{
    public const VERSION = '0.1.0';

    /** Providers registered before anything discovered or supplied by the theme. */
    public const CORE_PROVIDERS = [
        \Illuminate\Filesystem\FilesystemServiceProvider::class,
        \Illuminate\Database\DatabaseServiceProvider::class,
        \Illuminate\Encryption\EncryptionServiceProvider::class,
        \Illuminate\Hashing\HashServiceProvider::class,
        \Illuminate\Translation\TranslationServiceProvider::class,
        \Illuminate\Validation\ValidationServiceProvider::class,
        \Illuminate\Cache\CacheServiceProvider::class,
        \Illuminate\Cookie\CookieServiceProvider::class,
        \Illuminate\Session\SessionServiceProvider::class,
        \Illuminate\Auth\AuthServiceProvider::class,
        \Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        \Illuminate\Bus\BusServiceProvider::class,
        \Illuminate\Queue\QueueServiceProvider::class,
        \MM\Meros\Foundations\View\ViewServiceProvider::class,
        \MM\Meros\Foundations\Providers\FoundationServiceProvider::class,
        \MM\Meros\Foundations\Providers\ConsoleServiceProvider::class,
    ];

    protected string $basePath;
    protected array $paths = [];
    protected ?string $environmentPath = null;
    protected string $environmentFile = '.env';
    protected ?string $namespace = null;

    protected bool $hasBeenBootstrapped = false;
    protected bool $booted = false;
    protected bool $handlesWordPress = false;
    protected array $providers = [];
    protected array $loadedProviders = [];
    protected array $extraProviders = [];
    protected array $bootingCallbacks = [];
    protected array $bootedCallbacks = [];
    protected array $terminatingCallbacks = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\\/');

        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(self::class, $this);
        $this->singleton(PackageManifest::class, fn () => $this->makeManifest());
        $this->bindPaths();
        $this->registerCoreAliases();
        $this->singleton('Illuminate\\Foundation\\Vite', fn () => new \MM\Meros\Foundations\Vite());
        $this->singleton(\Illuminate\Contracts\Console\Kernel::class, \MM\Meros\Foundations\Console\Kernel::class);
        $this->singleton(\Illuminate\Contracts\Http\Kernel::class, \MM\Meros\Foundations\Http\Kernel::class);
        $this->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, \MM\Meros\Foundations\Exceptions\Handler::class);

        foreach ([EventServiceProvider::class, ContextServiceProvider::class, LogServiceProvider::class, RoutingServiceProvider::class] as $provider) {
            $this->register($provider);
        }
    }

    // ---- Providers -------------------------------------------------------

    /** @param string[] $providers */
    public function withProviders(array $providers): static
    {
        $this->extraProviders = array_values(array_unique([...$this->extraProviders, ...$providers]));

        return $this;
    }

    public function registerConfiguredProviders()
    {
        $providers = [
            ...self::CORE_PROVIDERS,
            ...$this->make(PackageManifest::class)->providers(),
            ...(array) $this['config']->get('app.providers', []),
            ...$this->extraProviders,
        ];

        foreach (array_unique($providers) as $provider) {
            $this->register($provider);
        }
    }

    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        foreach ($provider->bindings ?? [] as $key => $value) {
            $this->bind($key, $value);
        }

        foreach ($provider->singletons ?? [] as $key => $value) {
            $this->singleton(is_int($key) ? $value : $key, is_int($key) ? null : $value);
        }

        $this->providers[]                    = $provider;
        $this->loadedProviders[$provider::class] = true;

        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    public function registerDeferredProvider($provider, $service = null)
    {
        $this->register($provider);
    }

    public function loadDeferredProviders()
    {
    }

    public function getProvider($provider)
    {
        $name = is_string($provider) ? $provider : $provider::class;

        foreach ($this->providers as $registered) {
            if ($registered instanceof $name) {
                return $registered;
            }
        }

        return null;
    }

    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : $provider::class;

        return array_values(array_filter($this->providers, fn ($p) => $p instanceof $name));
    }

    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->fire($this->bootingCallbacks);

        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;

        $this->fire($this->bootedCallbacks);
    }

    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }

    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->booted) {
            $callback($this);
        }
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    protected function fire(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }

    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
    }

    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped;
    }

    public function terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    public function terminate()
    {
        foreach ($this->terminatingCallbacks as $callback) {
            $this->call($callback);
        }
    }

    // ---- WordPress -------------------------------------------------------

    /** Whether unmatched requests are handed to WordPress through a catch-all route. */
    public function handleWordPressRequests(): static
    {
        $this->handlesWordPress = true;

        return $this;
    }

    public function handlesWordPressRequests(): bool
    {
        return $this->handlesWordPress;
    }

    // ---- Paths -----------------------------------------------------------

    /**
     * Override any of: app, bootstrap, config, database, lang, public, resources, storage, environment.
     */
    public function usePaths(array $paths): static
    {
        foreach ($paths as $type => $path) {
            if (!in_array($type, ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'storage', 'environment'], true)) {
                throw new RuntimeException("The {$type} path type is not supported.");
            }

            $type === 'environment'
                ? $this->environmentPath = rtrim($path, '\\/')
                : $this->paths[$type] = rtrim($path, '\\/');
        }

        $this->bindPaths();

        return $this;
    }

    protected function bindPaths(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.bootstrap', $this->bootstrapPath());
        $this->instance('path.lang', $this->langPath());
    }

    protected function joinPaths(string $base, string $path = ''): string
    {
        return $base . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\\/') : '');
    }

    public function basePath($path = '')
    {
        return $this->joinPaths($this->basePath, $path);
    }

    public function path($path = '')
    {
        return $this->joinPaths($this->paths['app'] ?? $this->basePath('app'), $path);
    }

    public function configPath($path = '')
    {
        return $this->joinPaths($this->paths['config'] ?? $this->basePath('config'), $path);
    }

    public function databasePath($path = '')
    {
        return $this->joinPaths($this->paths['database'] ?? $this->basePath('database'), $path);
    }

    public function resourcePath($path = '')
    {
        return $this->joinPaths($this->paths['resources'] ?? $this->basePath('resources'), $path);
    }

    public function publicPath($path = '')
    {
        return $this->joinPaths($this->paths['public'] ?? $this->basePath('public'), $path);
    }

    public function storagePath($path = '')
    {
        return $this->joinPaths($this->paths['storage'] ?? $this->basePath('storage'), $path);
    }

    public function bootstrapPath($path = '')
    {
        return $this->joinPaths($this->paths['bootstrap'] ?? $this->storagePath('framework'), $path);
    }

    public function langPath($path = '')
    {
        return $this->joinPaths($this->paths['lang'] ?? $this->resourcePath('lang'), $path);
    }

    public function getCachedConfigPath(): string
    {
        return $this->bootstrapPath('cache/config.php');
    }

    public function getCachedPackagesPath(): string
    {
        return $this->bootstrapPath('cache/packages.php');
    }

    public function configurationIsCached(): bool
    {
        return is_file($this->getCachedConfigPath());
    }

    /** Resolve `.env` from the closest parent directory that has one. */
    public function environmentPath(): string
    {
        if ($this->environmentPath) {
            return $this->environmentPath;
        }

        for ($dir = $this->basePath(); dirname($dir) !== $dir; $dir = dirname($dir)) {
            if (is_file("{$dir}/{$this->environmentFile}")) {
                return $dir;
            }
        }

        return $this->basePath();
    }

    public function environmentFile(): string
    {
        return $this->environmentFile;
    }

    public function version()
    {
        return 'Meros Foundations ' . static::VERSION;
    }

    // ---- Environment -----------------------------------------------------

    public function environment(...$environments)
    {
        $current = $this->bound('config') ? $this['config']->get('app.env', 'production') : (Env::get('WP_ENV') ?: 'production');

        return $environments ? in_array($current, array_merge(...array_map('Illuminate\Support\Arr::wrap', $environments)), true) : $current;
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function runningInConsole()
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    }

    public function runningUnitTests()
    {
        return false;
    }

    public function hasDebugModeEnabled()
    {
        return (bool) $this['config']->get('app.debug', false);
    }

    public function maintenanceMode()
    {
        return null;
    }

    public function isDownForMaintenance()
    {
        return is_file($this->storagePath('framework/down')) || defined('ABSPATH') && is_file(ABSPATH . '.maintenance');
    }

    public function shouldSkipMiddleware()
    {
        return false;
    }

    public function getLocale()
    {
        return $this['config']->get('app.locale');
    }

    public function getFallbackLocale()
    {
        return $this['config']->get('app.fallback_locale');
    }

    public function setLocale($locale)
    {
        $this['config']->set('app.locale', $locale);
    }

    // ---- Namespace -------------------------------------------------------

    public function useNamespace(string $namespace): static
    {
        $this->namespace = trim($namespace, '\\') . '\\';

        return $this;
    }

    public function getNamespace()
    {
        if ($this->namespace !== null) {
            return $this->namespace;
        }

        $file = (new Filesystem())->exists($c = $this->basePath('composer.json')) ? $c : null;

        foreach ((array) data_get(json_decode($file ? file_get_contents($file) : '[]', true), 'autoload.psr-4') as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if (realpath($this->path()) === realpath($this->basePath($path))) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

    // ---- Internals -------------------------------------------------------

    protected function makeManifest(): PackageManifest
    {
        $paths = [$this->basePath(), get_template_directory(), get_stylesheet_directory()];

        if (defined('WP_CONTENT_DIR')) {
            $paths[] = WP_CONTENT_DIR;
        }

        $paths = apply_filters('meros/composer_paths', $paths);
        $paths = array_filter(array_unique(array_map(fn ($p) => rtrim($p, '/\\'), $paths)), fn ($p) => is_file("{$p}/composer.json"));

        return new PackageManifest(new Filesystem(), $paths, $this->getCachedPackagesPath());
    }

    protected function registerCoreAliases(): void
    {
        $aliases = [
            'app'           => [self::class, \Illuminate\Contracts\Container\Container::class, ApplicationContract::class, \Psr\Container\ContainerInterface::class],
            'auth'          => [\Illuminate\Auth\AuthManager::class, \Illuminate\Contracts\Auth\Factory::class],
            'auth.driver'   => [\Illuminate\Contracts\Auth\Guard::class],
            'auth.password' => [\Illuminate\Auth\Passwords\PasswordBrokerManager::class, \Illuminate\Contracts\Auth\PasswordBrokerFactory::class],
            'auth.password.broker' => [\Illuminate\Auth\Passwords\PasswordBroker::class, \Illuminate\Contracts\Auth\PasswordBroker::class],
            'hash'          => [\Illuminate\Hashing\HashManager::class],
            'hash.driver'   => [\Illuminate\Contracts\Hashing\Hasher::class],
            'queue'         => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
            'queue.failer'  => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
            'blade.compiler' => [\Illuminate\View\Compilers\BladeCompiler::class],
            'cache'         => [\Illuminate\Cache\CacheManager::class, \Illuminate\Contracts\Cache\Factory::class],
            'cache.store'   => [\Illuminate\Cache\Repository::class, \Illuminate\Contracts\Cache\Repository::class],
            'config'        => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'cookie'        => [\Illuminate\Cookie\CookieJar::class, \Illuminate\Contracts\Cookie\Factory::class, \Illuminate\Contracts\Cookie\QueueingFactory::class],
            'db'            => [\Illuminate\Database\DatabaseManager::class, \Illuminate\Database\ConnectionResolverInterface::class],
            'db.connection' => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
            'encrypter'     => [\Illuminate\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\Encrypter::class, \Illuminate\Contracts\Encryption\StringEncrypter::class],
            'events'        => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
            'files'         => [Filesystem::class],
            'log'           => [\Illuminate\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
            'redirect'      => [\Illuminate\Routing\Redirector::class],
            'request'       => [\Illuminate\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class],
            'router'        => [\Illuminate\Routing\Router::class, \Illuminate\Contracts\Routing\Registrar::class, \Illuminate\Contracts\Routing\BindingRegistrar::class],
            'session'       => [\Illuminate\Session\SessionManager::class],
            'session.store' => [\Illuminate\Session\Store::class, \Illuminate\Contracts\Session\Session::class],
            'translator'    => [\Illuminate\Translation\Translator::class, \Illuminate\Contracts\Translation\Translator::class],
            'validator'     => [\Illuminate\Validation\Factory::class, \Illuminate\Contracts\Validation\Factory::class],
            'url'           => [\Illuminate\Routing\UrlGenerator::class, \Illuminate\Contracts\Routing\UrlGenerator::class],
            'view'          => [\Illuminate\View\Factory::class, \Illuminate\Contracts\View\Factory::class],
        ];

        foreach ($aliases as $key => $list) {
            foreach ($list as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    public function flush()
    {
        parent::flush();

        $this->providers = $this->loadedProviders = [];
        $this->booted    = $this->hasBeenBootstrapped = false;
        Facade::clearResolvedInstances();
    }
}
