<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Support\ServiceProvider;
use MM\Meros\Foundations\PackageManifest;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Tests Composer provider discovery, manifest cache invalidation and package discovery exclusions.
 */
final class PackageManifestTest extends TestCase
{
    private function installMetadata(): void
    {
        $this->writeFile('vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'acme/meros-pkg', 'extra' => ['meros' => ['provider' => 'Acme\\Pkg\\Provider']]],
            ['name' => 'acme/laravel-pkg', 'extra' => ['laravel' => ['providers' => [DiscoveredProvider::class]]]],
            ['name' => 'acme/second', 'extra' => ['meros' => ['providers' => ['Acme\\Second\\Provider']]]],
        ]], JSON_THROW_ON_ERROR));
    }

    public function test_discovery_separates_laravel_and_meros_providers(): void
    {
        $this->installMetadata();
        $app = $this->createApplication();
        $manifest = $app->make(PackageManifest::class);
        $this->assertTrue($app['test.discovered']);
        $this->assertSame(['Acme\\Pkg\\Provider', 'Acme\\Second\\Provider'], $manifest->merosProviders());
        $this->assertSame([DiscoveredProvider::class], $manifest->providers());
        $this->assertSame(['name' => 'acme/meros-pkg', 'providers' => ['Acme\\Pkg\\Provider']], $manifest->package('meros-pkg'));
        $this->assertSame([DiscoveredProvider::class], $manifest->package('acme/laravel-pkg')['providers']);
        $this->assertNull($manifest->package('missing'));
    }

    public function test_changed_installed_metadata_invalidates_the_cache(): void
    {
        $this->installMetadata();
        $app = $this->createApplication();
        $file = $this->writeFile('vendor/composer/installed.json', '{"packages":[]}');
        touch($file, filemtime($app->getCachedPackagesPath()) + 1);
        clearstatcache(true, $file);
        $manifest = new PackageManifest($this->files, [$this->directory], $app->getCachedPackagesPath());
        $this->assertSame([], $manifest->merosProviders());
    }

    public function test_root_dont_discover_excludes_a_package(): void
    {
        $this->installMetadata();
        $this->writeFile('composer.json', '{"name":"test/theme","extra":{"meros":{"dont-discover":["acme/meros-pkg"]}}}');
        $app = $this->createApplication();
        $this->assertSame(['Acme\\Second\\Provider'], $app->make(PackageManifest::class)->merosProviders());
    }
}

class DiscoveredProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('test.discovered', true);
    }
}
