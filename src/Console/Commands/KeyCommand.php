<?php

namespace MM\Meros\Foundations\Console\Commands;

use Illuminate\Encryption\Encrypter;

/** Application key management. */
class KeyCommand extends Command
{
    /**
     * Generate an application key and write it to .env.
     *
     * ## OPTIONS
     *
     * [--show]
     * : Print the key instead of writing it.
     *
     * [--force]
     * : Overwrite an existing key.
     *
     * @subcommand generate
     */
    public function generate($args, $assoc)
    {
        $cipher = $this->app['config']['app.cipher'] ?? 'AES-256-CBC';
        $key    = 'base64:' . base64_encode(Encrypter::generateKey($cipher));

        if (!empty($assoc['show'])) {
            \WP_CLI::line($key);

            return;
        }

        $env     = $this->app->environmentPath() . '/' . $this->app->environmentFile();
        $current = $this->app['config']['app.key'];

        if ($current && empty($assoc['force'])) {
            \WP_CLI::error('An application key already exists. Use --force to replace it.');
        }

        $contents = is_file($env) ? file_get_contents($env) : '';

        $contents = preg_match('/^APP_KEY=.*$/m', $contents)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents)
            : rtrim($contents) . PHP_EOL . 'APP_KEY=' . $key . PHP_EOL;

        file_put_contents($env, ltrim($contents));

        \WP_CLI::success('Application key set.');
    }
}
