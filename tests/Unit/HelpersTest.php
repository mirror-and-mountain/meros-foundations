<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Support\Facades\Date;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Verifies that the now() helper respects the configured date factory and requested timezone.
 */
final class HelpersTest extends TestCase
{
    public function test_now_respects_the_date_factory_clock_and_requested_timezone(): void
    {
        $this->createApplication();
        Date::setTestNow('2026-07-01 12:00:00 UTC');
        try {
            $this->assertSame('2026-07-01T12:00:00+00:00', now()->toIso8601String());
            $this->assertSame('2026-07-01T13:00:00+01:00', now('Europe/London')->toIso8601String());
        } finally {
            Date::setTestNow();
        }
    }
}
