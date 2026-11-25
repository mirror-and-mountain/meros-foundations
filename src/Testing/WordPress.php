<?php

namespace MM\Meros\Foundations\Testing;

use RuntimeException;

final class WordPress
{
    public static function bootstrap(): void
    {
        if (defined('ABSPATH')) {
            throw new RuntimeException('Integration tests must run outside an existing WordPress process.');
        }
        $tests = rtrim(getenv('WP_TESTS_DIR') ?: '', '/\\');
        if (!$tests || !is_file($tests . '/includes/bootstrap.php')) {
            throw new RuntimeException('Set WP_TESTS_DIR to wordpress-develop/tests/phpunit.');
        }
        if (!class_exists(\Yoast\PHPUnitPolyfills\Autoload::class)) {
            throw new RuntimeException('Install development dependencies, including yoast/phpunit-polyfills.');
        }
        define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');
        require $tests . '/includes/bootstrap.php';
    }
}
