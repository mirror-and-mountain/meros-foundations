<?php

// WordPress's test installer drops tables. Require an explicitly named test database.
$database = getenv('MEROS_TEST_DB_NAME');
if (!$database || !preg_match('/^[A-Za-z0-9_]+_tests$/', $database)) {
    throw new RuntimeException('MEROS_TEST_DB_NAME must name a dedicated database ending in _tests.');
}
foreach (['WP_TESTS_ROOT', 'MEROS_TEST_DB_HOST', 'MEROS_TEST_DB_USER'] as $variable) {
    if (!getenv($variable)) {
        throw new RuntimeException("Set {$variable} before running integration tests.");
    }
}
$root = rtrim(getenv('WP_TESTS_ROOT'), '/\\') . '/';
if (!is_file($root . 'wp-settings.php')) {
    throw new RuntimeException('WP_TESTS_ROOT must point to a standalone WordPress source directory.');
}

define('ABSPATH', $root);
define('DB_NAME', $database);
define('DB_HOST', getenv('MEROS_TEST_DB_HOST'));
define('DB_USER', getenv('MEROS_TEST_DB_USER'));
define('DB_PASSWORD', getenv('MEROS_TEST_DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('WP_DEFAULT_THEME', 'default');
define('WP_TESTS_DOMAIN', 'meros.test');
define('WP_TESTS_EMAIL', 'admin@example.test');
define('WP_TESTS_TITLE', 'Meros Integration Tests');
define('WP_PHP_BINARY', PHP_BINARY);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENV', 'testing');
define('WPLANG', '');

global $table_prefix;
$table_prefix = 'wptests_';
