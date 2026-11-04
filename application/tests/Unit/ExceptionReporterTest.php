<?php

namespace Tests\Unit;

use App\Exceptions\ExceptionReporter;
use App\Services\ExceptionNotifier;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExceptionReporterTest extends TestCase
{
    public function test_client_errors_do_not_trigger_notifications(): void
    {
        $notifier = Mockery::mock(ExceptionNotifier::class);

        $notifier->shouldNotReceive('notify');

        $reporter = new ExceptionReporter($notifier);

        $reporter->report(
            new RuntimeException('Not found'),
            ['status' => 404]
        );
    }

    public function test_server_errors_trigger_notifications(): void
    {
        $exception = new RuntimeException('Unexpected failure');

        $notifier = Mockery::mock(ExceptionNotifier::class);

        $notifier->shouldReceive('notify')
            ->once()
            ->with(
                $exception,
                'System Error',
                500,
                []
            );

        $reporter = new ExceptionReporter($notifier);

        $reporter->report(
            $exception,
            [
                'status' => 500,
                'message' => 'System Error',
                'errors' => [],
            ]
        );
    }

    public function test_notification_failures_do_not_interrupt_exception_handling(): void
    {
        $notifier = Mockery::mock(ExceptionNotifier::class);

        $notifier->shouldReceive('notify')
            ->once()
            ->andThrow(new RuntimeException('Slack unavailable'));

        $reporter = new ExceptionReporter($notifier);

        $reporter->report(
            new RuntimeException('Unexpected failure'),
            [
                'status' => 500,
                'message' => 'System Error',
                'errors' => [],
            ]
        );

        $this->assertTrue(true);
    }
}
