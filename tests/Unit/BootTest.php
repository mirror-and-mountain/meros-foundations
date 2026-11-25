<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\Bootstrap\LoadConfiguration;
use MM\Meros\Foundations\Config\Stubs;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Tests isolated application boot, configuration layering, paths, views, routing, logging and publishing.
 */
final class BootTest extends TestCase
{
    public function test_configuration_layers_and_storage_paths(): void
    {
        $this->writeFile('stubs/view.php', '<?php return ["framework_stub" => true];');
        $app = $this->createApplication(config: ['app' => ['name' => 'Overridden']], stubs: [$this->directory . '/stubs']);
        $this->assertSame($this->directory . '/storage', $app->storagePath());
        $this->assertSame('testing', $app['config']->get('app.env'));
        $this->assertSame('en_GB', $app['config']->get('app.locale'));
        $this->assertSame('Overridden', $app['config']->get('app.name'));
        $this->assertTrue($app['config']->get('view.framework_stub'));
        $this->assertSame('mysql', $app['config']->get('database.connections.wordpress.driver'));
        $this->assertNotNull($app['config']->get('cache.stores.file'));
        $items = LoadConfiguration::items($app);
        $path = $this->writeFile('cached-items.php', '<?php return ' . var_export($items, true) . ';');
        $this->assertSame($items, require $path);
    }

    public function test_blade_routes_and_configured_aliases(): void
    {
        $this->writeFile('routes/web.php', '<?php Illuminate\Support\Facades\Route::get("/hi", fn () => view("hello", ["who" => "Meros"]));');
        $this->writeFile('resources/views/hello.blade.php', 'Hello {{ Str::upper($who) }} from {{ config("app.name") }}');
        $app = $this->createApplication(config: ['app' => [
            'name' => 'Overridden',
            'aliases' => ['CustomStr' => \Illuminate\Support\Str::class],
        ]]);
        $this->assertFalse($app['view']->exists('meros-test::missing'));
        $response = $app->make(Kernel::class)->handle(Request::create('/hi'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello MEROS from Overridden', trim($response->getContent()));
        $this->assertSame('CUSTOM', \CustomStr::upper('custom'));
    }

    public function test_logging_includes_context_without_emergency_fallback(): void
    {
        $app = $this->createApplication();
        Context::add('request_id', 'test-123');
        $app['log']->info('Configured logger works');
        $log = $this->files->get($app->storagePath('logs/meros.log'));
        $this->assertStringContainsString('Configured logger works', $log);
        $this->assertStringContainsString('test-123', $log);
        $this->assertStringNotContainsString('Unable to create configured logger', $log);
    }

    public function test_fluent_route_names_resolve_for_theme_and_package_routes_before_dispatch(): void
    {
        $this->writeFile('routes/web.php', <<<'PHP'
<?php
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('login', fn () => 'Login')->name('login');
    Route::get('/', fn () => 'Dashboard')
        ->middleware(\MM\Meros\Foundations\Tests\Unit\NamedRouteRedirect::class)
        ->name('dashboard');
});
PHP);
        $app = $this->createApplication([NamedRoutesProvider::class]);

        $this->assertSame('/portal/login', route('portal.login', [], false));
        $this->assertSame('/package/login', route('package.login', [], false));
        $this->assertTrue(Route::has('portal.dashboard'));

        $response = $app->make(Kernel::class)->handle(Request::create('/portal'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('portal.login'), $response->headers->get('Location'));
    }

    public function test_publishing_uses_the_highest_priority_stub(): void
    {
        $this->writeFile('stubs/view.php', '<?php return ["framework_stub" => true];');
        $this->createApplication(stubs: [$this->directory . '/stubs']);
        $published = Stubs::publish($this->directory . '/published');
        $this->assertContains('app', $published);
        $this->assertContains('view', $published);
        $this->assertTrue((require $this->directory . '/published/view.php')['framework_stub']);
    }
}

class NamedRoutesProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('package')->name('package.')->group(function () {
            Route::get('login', fn () => 'Package login')->name('login');
        });
    }
}

class NamedRouteRedirect
{
    public function handle(Request $request, \Closure $next)
    {
        return redirect()->route('portal.login');
    }
}
