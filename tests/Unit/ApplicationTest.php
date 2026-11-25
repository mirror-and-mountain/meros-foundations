<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\Bootstrap\SetRequestForConsole;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Tests provider boot ordering and console request availability during application startup.
 */
final class ApplicationTest extends TestCase
{
    public function test_providers_registered_during_boot_are_booted_once(): void
    {
        $app = $this->createApplication([RegisteringProvider::class]);
        $app->boot();
        $this->assertSame(['parent.boot', 'child.register', 'child.boot', 'app.booted'], $app['test.events']);
        $this->assertNotNull($app->getProvider(NestedProvider::class));
    }

    public function test_console_request_exists_before_providers_register(): void
    {
        $app = $this->createApplication([RequestProvider::class], ['app' => ['url' => 'https://example.test/site']]);
        $this->assertSame('https://example.test/site/probe', $app['test.url']);
    }

    public function test_console_bootstrap_preserves_an_existing_request(): void
    {
        $app = $this->createApplication();
        $request = Request::create('https://example.test/existing');
        $app->instance('request', $request);
        (new SetRequestForConsole())->bootstrap($app);
        $this->assertSame($request, $app['request']);
    }
}

class RegisteringProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('test.events', []);
        $this->app->booted(function ($app) {
            $app['test.events'] = [...$app['test.events'], 'app.booted'];
        });
    }

    public function boot(): void
    {
        $this->app['test.events'] = [...$this->app['test.events'], 'parent.boot'];
        $this->app->register(NestedProvider::class);
        $this->app->register(NestedProvider::class);
    }
}

class NestedProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['test.events'] = [...$this->app['test.events'], 'child.register'];
    }

    public function boot(): void
    {
        $this->app['test.events'] = [...$this->app['test.events'], 'child.boot'];
    }
}

class RequestProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('test.url', $this->app['url']->to('/probe'));
    }
}
