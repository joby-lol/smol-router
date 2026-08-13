<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use PHPUnit\Framework\TestCase;
use Joby\Smol\Request\Request;
use Joby\Smol\Response\Response;

class AbstractMiddlewareTest extends TestCase
{

    private function createMockRequest(): Request
    {
        return $this->createMock(Request::class);
    }

    private function createMockRouteContext(): RouteContext
    {
        return $this->createMock(RouteContext::class);
    }

    private function createMockResponse(): Response
    {
        return $this->createMock(Response::class);
    }

    private function createMockRouteError(int $code = 404): RouteError
    {
        $context = $this->createMockRouteContext();
        return new RouteError($code, $context, 'Test error');
    }

    public function test_default_implementation_passes_through_request_to_next(): void
    {
        $middleware =

            new class extends AbstractMiddleware {};

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();
        $expectedResponse = $this->createMockResponse();

        $nextCalled = false;
        $next = function () use ($expectedResponse, &$nextCalled) {
            $nextCalled = true;
            return $expectedResponse;
        };

        $result = $middleware($request, $next, $context);

        $this->assertTrue($nextCalled);
        $this->assertSame($expectedResponse, $result);
    }

    public function test_process_request_returning_response_short_circuits_next(): void
    {
        $earlyResponse = $this->createMockResponse();

        $middleware =

            new class ($earlyResponse) extends AbstractMiddleware {

            public function __construct(private Response $earlyResponse) {}

            protected function processRequest(Request $request, RouteContext $context): Response|RouteError|null
            {
                return $this->earlyResponse;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
            return $this->createMockResponse();
        };

        $result = $middleware($request, $next, $context);

        $this->assertFalse($nextCalled);
        $this->assertSame($earlyResponse, $result);
    }

    public function test_process_request_returning_route_error_short_circuits_next(): void
    {
        $earlyError = $this->createMockRouteError(401);

        $middleware =

            new class ($earlyError) extends AbstractMiddleware {

            public function __construct(private RouteError $earlyError) {}

            protected function processRequest(Request $request, RouteContext $context): Response|RouteError|null
            {
                return $this->earlyError;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
            return $this->createMockResponse();
        };

        $result = $middleware($request, $next, $context);

        $this->assertFalse($nextCalled);
        $this->assertSame($earlyError, $result);
    }

    public function test_process_response_can_modify_or_replace_response(): void
    {
        $innerResponse = $this->createMockResponse();
        $modifiedResponse = $this->createMockResponse();

        $middleware =

            new class ($innerResponse, $modifiedResponse) extends AbstractMiddleware {

            public function __construct(
            private Response $innerResponse,
            private Response $modifiedResponse,
            ) {}

            protected function processResponse(Response $response, RouteContext $context): Response|RouteError
            {
                if ($response === $this->innerResponse) {
                    return $this->modifiedResponse;
                }
                return $response;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $next = fn() => $innerResponse;

        $result = $middleware($request, $next, $context);

        $this->assertSame($modifiedResponse, $result);
    }

    public function test_process_error_can_modify_or_intercept_route_error(): void
    {
        $innerError = $this->createMockRouteError(500);
        $transformedError = $this->createMockRouteError(503);

        $middleware =

            new class ($transformedError) extends AbstractMiddleware {

            public function __construct(private RouteError $transformedError) {}

            protected function processError(RouteError $error, RouteContext $context): Response|RouteError
            {
                return $this->transformedError;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $next = fn() => $innerError;

        $result = $middleware($request, $next, $context);

        $this->assertSame($transformedError, $result);
    }

    public function test_process_response_runs_on_early_response_from_process_request(): void
    {
        $earlyResponse = $this->createMockResponse();
        $finalResponse = $this->createMockResponse();

        $middleware =

            new class ($earlyResponse, $finalResponse) extends AbstractMiddleware {

            public function __construct(
            private Response $earlyResponse,
            private Response $finalResponse,
            ) {}

            protected function processRequest(Request $request, RouteContext $context): Response|RouteError|null
            {
                return $this->earlyResponse;
            }

            protected function processResponse(Response $response, RouteContext $context): Response|RouteError
            {
                return $this->finalResponse;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $next = fn() => $this->createMockResponse();

        $result = $middleware($request, $next, $context);

        $this->assertSame($finalResponse, $result);
    }

    public function test_process_error_runs_on_early_error_from_process_request(): void
    {
        $earlyError = $this->createMockRouteError(403);
        $finalError = $this->createMockRouteError(403);

        $middleware =

            new class ($earlyError, $finalError) extends AbstractMiddleware {

            public function __construct(
            private RouteError $earlyError,
            private RouteError $finalError,
            ) {}

            protected function processRequest(Request $request, RouteContext $context): Response|RouteError|null
            {
                return $this->earlyError;
            }

            protected function processError(RouteError $error, RouteContext $context): Response|RouteError
            {
                return $this->finalError;
            }

            };

        $request = $this->createMockRequest();
        $context = $this->createMockRouteContext();

        $next = fn() => $this->createMockResponse();

        $result = $middleware($request, $next, $context);

        $this->assertSame($finalError, $result);
    }

}
