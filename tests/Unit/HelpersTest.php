<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Support\Facades\Date;
use Illuminate\Contracts\Debug\ExceptionHandler;
use LogicException;
use MM\Meros\Foundations\Testing\TestCase;

/**
 * Verifies date helpers and exception recovery compatibility.
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

    public function test_rescue_returns_the_callback_result(): void
    {
        $this->assertSame('success', rescue(fn () => 'success', fn () => $this->fail('Unexpected fallback.')));
    }

    public function test_rescue_reports_failures_and_passes_the_exception_to_the_fallback(): void
    {
        $app = $this->createApplication();
        $exception = new LogicException('Callback failed.');
        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects($this->once())->method('report')->with($exception);
        $app->instance(ExceptionHandler::class, $handler);

        $this->assertSame('Callback failed.', rescue(
            fn () => throw $exception,
            fn ($failure) => $failure->getMessage()
        ));
    }

    public function test_rescue_only_skips_reporting_when_explicitly_requested(): void
    {
        $app = $this->createApplication();
        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects($this->never())->method('report');
        $app->instance(ExceptionHandler::class, $handler);

        foreach ([false, fn ($exception) => !($exception instanceof LogicException)] as $report) {
            $this->assertNull(rescue(fn () => throw new LogicException('Optional resource missing.'), null, $report));
        }
    }
}
