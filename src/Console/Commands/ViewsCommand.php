<?php

namespace MM\Meros\Foundations\Console\Commands;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Manage compiled Blade views.
 */
class ViewsCommand extends Command
{
    /**
     * Delete all compiled views.
     *
     * @subcommand clear
     */
    public function clear()
    {
        $path = $this->app['config']['view.compiled'];

        if (!$path) {
            \WP_CLI::error('View compiled path not found.');
        }

        foreach (File::glob(rtrim($path, '/') . '/*') as $view) {
            File::delete($view);
        }

        \WP_CLI::success('Compiled views cleared.');
    }

    /**
     * Compile every Blade template in the view paths and namespaces.
     *
     * @subcommand cache
     */
    public function cache()
    {
        $this->clear();

        $compiler = $this->app['view']->getEngineResolver()->resolve('blade')->getCompiler();
        $finder   = $this->app['view.finder'];
        $paths    = collect($finder->getPaths())
            ->merge(collect($finder->getHints())->flatten())
            ->unique()
            ->filter(fn ($path) => is_dir($path))
            ->values()
            ->all();

        if (!$paths) {
            \WP_CLI::warning('No view paths found.');

            return;
        }

        $count = 0;

        foreach (Finder::create()->in($paths)->exclude('vendor')->name('*.blade.php')->files() as $file) {
            $compiler->compile($file->getRealPath());
            $count++;
        }

        \WP_CLI::success("Compiled {$count} Blade templates.");
    }
}
