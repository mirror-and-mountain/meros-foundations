<?php

namespace MM\Meros\Foundations\View;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\DynamicComponent;
use Illuminate\View\ViewServiceProvider as BaseViewServiceProvider;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Blade view resolution for WordPress themes. Adapted from roots/acorn (MIT).
 *
 * Config: `view.paths`, `view.namespaces`, `view.directives`, `view.components`,
 * `view.composers`; composers in `app/View/Composers` are auto-discovered.
 */
class ViewServiceProvider extends BaseViewServiceProvider
{
    public function boot()
    {
        $this->attachDirectives();
        $this->attachComponents();
        $this->attachComposers();
    }

    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            $finder = new FileViewFinder($app['files'], array_unique($app['config']['view.paths']));

            foreach ($app['config']->get('view.namespaces', []) as $namespace => $hints) {
                // Theme overrides: {view path}/vendor/{namespace}
                $hints = array_merge(
                    array_map(fn ($path) => "{$path}/vendor/{$namespace}", $finder->getPaths()),
                    (array) $hints,
                );

                $finder->addNamespace($namespace, $hints);
            }

            return $finder;
        });

        $this->app->alias('view.finder', FileViewFinder::class);
    }

    public function registerBladeCompiler()
    {
        $this->app->singleton('blade.compiler', function ($app) {
            return tap(
                new BladeCompiler(
                    $app['files'],
                    $app['config']['view.compiled'],
                    $app['config']->get('view.relative_hash', false) ? $this->relativeHashPath() : '',
                    $app['config']->get('view.cache', true),
                    $app['config']->get('view.compiled_extension', 'php'),
                    $app['config']->get('view.check_cache_timestamps', true),
                ),
                fn ($blade) => $blade->component('dynamic-component', DynamicComponent::class),
            );
        });
    }

    /** Relative hashes start at the content directory so themes/plugins sharing a compiled path don't collide. */
    protected function relativeHashPath(): string
    {
        $base = realpath($this->app->basePath()) ?: $this->app->basePath();

        if (!defined('WP_CONTENT_DIR')) {
            return $base;
        }

        $content = rtrim(realpath(WP_CONTENT_DIR) ?: WP_CONTENT_DIR, '\\/');

        return str_starts_with($base, $content . DIRECTORY_SEPARATOR) ? $content : $base;
    }

    protected function blade(): BladeCompiler
    {
        return $this->app['view']->getEngineResolver()->resolve('blade')->getCompiler();
    }

    protected function attachDirectives(): void
    {
        foreach ($this->app['config']->get('view.directives', []) as $name => $handler) {
            $this->blade()->directive($name, is_callable($handler) ? $handler : $this->app->make($handler));
        }
    }

    protected function attachComponents(): void
    {
        $components = $this->app['config']->get('view.components');

        if (is_array($components) && Arr::isAssoc($components)) {
            foreach ($components as $alias => $view) {
                $this->blade()->component($view, $alias);
            }
        }
    }

    protected function attachComposers(): void
    {
        $view      = $this->app['view'];
        $composers = $this->app['config']->get('view.composers');

        if (is_array($composers) && Arr::isAssoc($composers)) {
            foreach ($composers as $composer) {
                $view->composer($composer::views(), $composer);
            }
        }

        if (!is_dir($path = $this->app->path('View/Composers'))) {
            return;
        }

        try {
            $namespace = $this->app->getNamespace();
        } catch (RuntimeException) {
            return;
        }

        foreach ((new Finder())->in($path)->files() as $file) {
            $class = $namespace . str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), $this->app->path() . DIRECTORY_SEPARATOR));

            if (is_subclass_of($class, Composer::class) && !(new ReflectionClass($class))->isAbstract()) {
                $view->composer($class::views(), $class);
            }
        }
    }
}
