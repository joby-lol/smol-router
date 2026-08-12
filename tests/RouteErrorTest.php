<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Exception;
use PHPUnit\Framework\TestCase;

class RouteErrorTest extends TestCase
{

    private function createMockContext(): RouteContext
    {
        return $this->createMock(RouteContext::class);
    }

    public function test_constructor_initialization(): void
    {
        $context = $this->createMockContext();
        $exception = new Exception('Testing exception');

        $error = new RouteError(
            404,
            $context,
            'Not Found',
            $exception,
        );

        $this->assertSame(404, $error->http_code);
        $this->assertSame($context, $error->context);
        $this->assertSame('Not Found', $error->message);
        $this->assertSame($exception, $error->exception);
    }

    public function test_is_hard_failure_returns_true_for_403(): void
    {
        $error = new RouteError(403, $this->createMockContext(), 'Access Denied');

        $this->assertTrue($error->isHardFailure());
    }

    public function test_is_hard_failure_returns_true_for_500_or_higher(): void
    {
        $error500 = new RouteError(500, $this->createMockContext(), 'Internal Server Error');
        $error503 = new RouteError(503, $this->createMockContext(), 'Service Unavailable');

        $this->assertTrue($error500->isHardFailure());
        $this->assertTrue($error503->isHardFailure());
    }

    public function test_is_hard_failure_returns_true_when_exception_is_present(): void
    {
        $error = new RouteError(
            404,
            $this->createMockContext(),
            'Soft code with exception',
            new Exception('Something broke'),
        );

        $this->assertTrue($error->isHardFailure());
    }

    public function test_is_hard_failure_returns_false_for_soft_errors(): void
    {
        $error404 = new RouteError(404, $this->createMockContext(), 'Not Found');
        $error405 = new RouteError(405, $this->createMockContext(), 'Method Not Allowed');
        $error400 = new RouteError(400, $this->createMockContext(), 'Bad Request');

        $this->assertFalse($error404->isHardFailure());
        $this->assertFalse($error405->isHardFailure());
        $this->assertFalse($error400->isHardFailure());
    }

}
