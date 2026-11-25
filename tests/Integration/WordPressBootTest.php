<?php

namespace MM\Meros\Foundations\Tests\Integration;

use MM\Meros\Foundations\Testing\WordPressTestCase;

final class WordPressBootTest extends WordPressTestCase
{
    public function test_application_uses_the_wordpress_test_database(): void
    {
        global $wpdb;
        $this->assertTrue($this->app->isBooted());
        $this->assertSame($wpdb->prefix, $this->app['db']->connection()->getTablePrefix());
        $this->assertSame(DB_NAME, $this->app['db']->connection()->getDatabaseName());
        $this->assertSame(get_option('siteurl'), $this->app['db']->table('options')->where('option_name', 'siteurl')->value('option_value'));
    }
}
