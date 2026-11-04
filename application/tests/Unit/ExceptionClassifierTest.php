<?php

namespace Tests\Unit;

use App\Exceptions\ExceptionClassifier;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ExceptionClassifierTest extends TestCase
{
    private ExceptionClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new ExceptionClassifier();
    }

    public function test_not_found_exception(): void
    {
        $result = $this->classifier->classify(
            new NotFoundHttpException()
        );

        $this->assertSame(404, $result['status']);
        $this->assertSame('Not Found', $result['message']);
        $this->assertSame([], $result['errors']);
    }

    public function test_rate_limit_exception(): void
    {
        $result = $this->classifier->classify(
            new TooManyRequestsHttpException(60)
        );

        $this->assertSame(429, $result['status']);
        $this->assertSame(60, $result['headers']['Retry-After']);
    }

    public function test_validation_exception(): void
    {
        $exception = ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);

        $result = $this->classifier->classify($exception);

        $this->assertSame(422, $result['status']);
        $this->assertSame(
            ['email' => ['The email field is required.']],
            $result['errors']
        );
    }

    public function test_unexpected_exception(): void
    {
        $result = $this->classifier->classify(
            new RuntimeException('Unexpected failure')
        );

        $this->assertSame(500, $result['status']);
        $this->assertSame('System Error', $result['message']);
        $this->assertSame([], $result['errors']);
    }
}
