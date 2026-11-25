<?php

namespace MM\Meros\Foundations\Tests\Unit;

use MM\Meros\Foundations\Testing\TestCase;
use MM\Meros\Foundations\Testing\WordPress;
use RuntimeException;

/**
 * Verifies that integration bootstrap rejects an existing WordPress process or a development database.
 */
final class WordPressBootstrapTest extends TestCase
{
    public function test_integration_bootstrap_rejects_an_existing_wordpress_process(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integration tests must run outside an existing WordPress process.');
        WordPress::bootstrap();
    }

    public function test_config_rejects_a_development_database_before_loading_wordpress(): void
    {
        $original = getenv('MEROS_TEST_DB_NAME');
        putenv('MEROS_TEST_DB_NAME=wordpress');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('dedicated database ending in _tests');
            require dirname(__DIR__, 2) . '/src/Testing/wp-tests-config.php';
        } finally {
            putenv($original === false ? 'MEROS_TEST_DB_NAME' : 'MEROS_TEST_DB_NAME=' . $original);
        }
    }
}
