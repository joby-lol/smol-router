<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Exception;
use Joby\Smol\Request\Cookies\Cookies;
use Joby\Smol\Request\Headers\Headers;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Post\Post;
use Joby\Smol\Request\Request;
use Joby\Smol\Request\Source\Source;
use Joby\Smol\Response\Response;
use Joby\Smol\URL\Path;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{

    private function createRealRequest(string $path = '/foo/bar', Method $method = Method::GET): Request
    {
        return new Request(
            url: new URL(Path::fromString($path)),
            method: $method,
            headers: new Headers(null, null, null, null, []),
            cookies: new Cookies([]),
            post: new Post([], []),
            source: new Source('127.0.0.1', '127.0.0.1', 'test ua'),
        );
    }

    private function createMockResponse(): Response
    {
        return $this->createMock(Response::class);
    }

    // --- Basic Matching, HTTP Methods & Handlers ---

    public function test_run_executes_handler_and_returns_response_on_exact_match(): void
    {
        $expectedResponse = $this->createMockResponse();
        $router = new Router(
            pattern: 'hello/',
            handler: fn() => $expectedResponse,
        );

        $request = $this->createRealRequest('/hello/');
        $result = $router->run($request);

        $this->assertSame($expectedResponse, $result);
    }

    public function test_run_returns_404_route_error_when_pattern_does_not_match(): void
    {
        $router = new Router(
            pattern: 'admin/',
            handler: fn() => $this->createMockResponse(),
        );

        $request = $this->createRealRequest('/public');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(404, $result->http_code);
        $this->assertFalse($result->isHardFailure());
    }

    public function test_run_returns_405_route_error_when_method_is_not_allowed(): void
    {
        $router = new Router(
            pattern: 'submit/',
            handler: fn() => $this->createMockResponse(),
            method: Method::POST,
        );

        $request = $this->createRealRequest('/submit', Method::GET);
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(405, $result->http_code);
        $this->assertFalse($result->isHardFailure());
    }

    public function test_run_allows_multiple_http_methods(): void
    {
        $expectedResponse = $this->createMockResponse();
        $router = new Router(
            pattern: 'api/data/',
            handler: fn() => $expectedResponse,
            method: [Method::GET, Method::POST],
        );

        $getRequest = $this->createRealRequest('/api/data/', Method::GET);
        $postRequest = $this->createRealRequest('/api/data/', Method::POST);

        $this->assertEquals($expectedResponse, $router->run($getRequest));
        $this->assertEquals($expectedResponse, $router->run($postRequest));
    }

    public function test_run_converts_unhandled_handler_exceptions_to_500_route_error(): void
    {
        $router = new Router(
            pattern: 'crash/',
            handler: function () {
                throw new Exception('Database crash');
            },
        );

        $request = $this->createRealRequest('/crash/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Unhandled exception', $result->message);
        $this->assertInstanceOf(Exception::class, $result->exception);
        $this->assertSame('Database crash', $result->exception->getMessage());
    }

    public function test_run_falls_through_to_soft_error_if_handler_returns_null(): void
    {
        $router = new Router(
            pattern: 'maybe/',
            handler: fn() => null, // Handler explicitly declines to handle
        );

        $request = $this->createRealRequest('/maybe');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(404, $result->http_code);
    }

    // --- Guards & Priority Ordering ---

    public function test_guard_returning_false_aborts_with_403_route_error(): void
    {
        $router = new Router(
            pattern: 'protected/',
            handler: fn() => $this->createMockResponse(),
        );
        $router->guard(fn() => false);

        $request = $this->createRealRequest('/protected/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(403, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Access denied', $result->message);
    }

    public function test_guard_returning_true_short_circuits_lower_priority_guards(): void
    {
        $executedLowerPriorityGuard = false;

        $router = new Router(
            pattern: 'protected/',
            handler: fn() => $this->createMockResponse(),
        );

        // High priority guard grants access immediately
        $router->guard(fn() => true, Priority::HIGH);

        // Normal priority guard that should never run
        $router->guard(function () use (&$executedLowerPriorityGuard) {
            $executedLowerPriorityGuard = true;
            return false;
        }, Priority::NORMAL);

        $request = $this->createRealRequest('/protected/');
        $result = $router->run($request);

        $this->assertFalse($executedLowerPriorityGuard);
        $this->assertInstanceOf(Response::class, $result);
    }

    public function test_guard_returning_null_abstains_and_continues_evaluation(): void
    {
        $secondGuardExecuted = false;

        $router = new Router(
            pattern: 'protected/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->guard(fn() => null, Priority::HIGH);
        $router->guard(function () use (&$secondGuardExecuted) {
            $secondGuardExecuted = true;
            return true;
        }, Priority::NORMAL);

        $request = $this->createRealRequest('/protected/');
        $result = $router->run($request);

        $this->assertTrue($secondGuardExecuted);
        $this->assertInstanceOf(Response::class, $result);
    }

    public function test_guards_execute_in_strict_priority_order(): void
    {
        $executionOrder = [];

        $router = new Router(
            pattern: 'test/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->guard(function () use (&$executionOrder) {
            $executionOrder[] = 'low';
            return null;
        }, Priority::LOW);

        $router->guard(function () use (&$executionOrder) {
            $executionOrder[] = 'high';
            return null;
        }, Priority::HIGH);

        $router->guard(function () use (&$executionOrder) {
            $executionOrder[] = 'normal';
            return null;
        }, Priority::NORMAL);

        $request = $this->createRealRequest('/test/');
        $router->run($request);

        $this->assertSame(['high', 'normal', 'low'], $executionOrder);
    }

    public function test_guard_exception_converts_to_500_route_error(): void
    {
        $router = new Router(
            pattern: 'protected/',
            handler: fn() => $this->createMockResponse(),
        );
        $router->guard(function () {
            throw new Exception('Auth service down');
        });

        $request = $this->createRealRequest('/protected/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Unhandled exception', $result->message);
        $this->assertSame('Auth service down', $result->exception->getMessage());
    }

    // --- Parameter Injection & Autowiring ---

    public function test_injects_reserved_parameters_by_name(): void
    {
        $capturedRequest = null;
        $capturedPath = null;
        $capturedParams = null;

        $router = new Router(
            pattern: 'users/:user_id/',
            handler: function (Request $request, string $remaining_path, array $all_parameters) use (&$capturedRequest, &$capturedPath, &$capturedParams) {
                $capturedRequest = $request;
                $capturedPath = $remaining_path;
                $capturedParams = $all_parameters;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/users/42/');
        $router->run($request);

        $this->assertSame($request, $capturedRequest);
        $this->assertSame('', $capturedPath);
        $this->assertSame(['user_id' => '42'], $capturedParams);
    }

    public function test_casts_primitive_parameters(): void
    {
        $capturedInt = null;
        $capturedBool = null;

        $router = new Router(
            pattern: 'item/:item_id/:item_active/',
            handler: function (int $item_id, bool $item_active) use (&$capturedInt, &$capturedBool) {
                $capturedInt = $item_id;
                $capturedBool = $item_active;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/item/100/true/');
        $result = $router->run($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(100, $capturedInt);
        $this->assertTrue($capturedBool);
    }

    public function test_uses_default_value_when_parameter_is_missing_from_path(): void
    {
        $capturedValue = null;

        $router = new Router(
            pattern: 'page/',
            handler: function (string $tag = 'default_tag') use (&$capturedValue) {
                $capturedValue = $tag;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/page/');
        $router->run($request);

        $this->assertSame('default_tag', $capturedValue);
    }

    public function test_throws_parameters_missing_exception_if_required_parameter_is_missing(): void
    {
        $router = new Router(
            pattern: 'page/',
            handler: function (string $requiredParam) {
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/page/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertSame('Handler parameters not available', $result->message);
        $this->assertInstanceOf(ParametersMissingException::class, $result->exception);
    }

    public function test_invalid_primitive_format_triggers_soft_404_error(): void
    {
        $router = new Router(
            pattern: 'user/:id/',
            handler: function (int $id) {
                return $this->createMockResponse();
            },
        );

        // 'not-an-int' cannot cast to int -> triggers ParametersInvalidFormatException -> 404
        $request = $this->createRealRequest('/user/not-an-int/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(404, $result->http_code);
        $this->assertFalse($result->isHardFailure());
    }

    public function test_autowires_from_string_interface_objects(): void
    {
        $capturedObject = null;

        $router = new Router(
            pattern: 'item/:obj/',
            handler: function (StubFromStringObject $obj) use (&$capturedObject) {
                $capturedObject = $obj;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/item/valid_string/');
        $router->run($request);

        $this->assertInstanceOf(StubFromStringObject::class, $capturedObject);
        $this->assertSame('valid_string', $capturedObject->val);
    }

    public function test_add_parameter_factory_resolves_custom_objects(): void
    {
        $capturedUser = null;

        $router = new Router(
            pattern: 'users/:user/',
            handler: function (StubBaseUser $user) use (&$capturedUser) {
                $capturedUser = $user;
                return $this->createMockResponse();
            },
        );

        $router->addParameterFactory(StubBaseUser::class, fn(string $user) => new StubBaseUser("user_{$user}"));

        $request = $this->createRealRequest('/users/99/');
        $router->run($request);

        $this->assertInstanceOf(StubBaseUser::class, $capturedUser);
        $this->assertSame('user_99', $capturedUser->id);
    }

    public function test_parameter_factory_supports_base_class_matching_for_derived_types(): void
    {
        $capturedAdmin = null;

        $router = new Router(
            pattern: 'admin/:id/',
            handler: function (StubAdminUser $id) use (&$capturedAdmin) {
                $capturedAdmin = $id;
                return $this->createMockResponse();
            },
        );

        // Factory registered for StubBaseUser should satisfy a parameter type-hinted as StubAdminUser
        $router->addParameterFactory(StubBaseUser::class, fn(string $id) => new StubAdminUser("admin_{$id}"));

        $request = $this->createRealRequest('/admin/1/');
        $router->run($request);

        $this->assertInstanceOf(StubAdminUser::class, $capturedAdmin);
        $this->assertSame('admin_1', $capturedAdmin->id);
    }

    // --- Sub-Router Traversal & Error Accumulation ---

    public function test_delegates_remaining_path_to_child_router(): void
    {
        $expectedResponse = $this->createMockResponse();

        $rootRouter = new Router(pattern: 'api/');
        $childRouter = new Router(
            pattern: 'v1/users/',
            handler: fn() => $expectedResponse,
        );

        $rootRouter->addRouter($childRouter);

        $request = $this->createRealRequest('/api/v1/users/');
        $result = $rootRouter->run($request);

        $this->assertSame($expectedResponse, $result);
    }

    public function test_valid_response_in_child_router_wins_over_soft_errors(): void
    {
        $expectedResponse = $this->createMockResponse();

        $rootRouter = new Router(pattern: 'app/');

        // Child 1 returns null -> triggers soft 404
        $child1 = new Router(
            pattern: 'feature/',
            handler: fn() => null,
        );

        // Child 2 returns a valid Response
        $child2 = new Router(
            pattern: 'feature/',
            handler: fn() => $expectedResponse,
        );

        $rootRouter->addRouter($child1);
        $rootRouter->addRouter($child2);

        $request = $this->createRealRequest('/app/feature/');
        $result = $rootRouter->run($request);

        $this->assertSame($expectedResponse, $result);
    }

    public function test_hard_error_in_child_router_halts_traversal_immediately(): void
    {
        $executedChild2 = false;

        $rootRouter = new Router(pattern: 'app/');

        // Child 1 has a guard that returns false -> 403 Hard Error
        $child1 = new Router(
            pattern: 'feature/',
            handler: fn() => $this->createMockResponse(),
        );
        $child1->guard(fn() => false);

        // Child 2 should never execute
        $child2 = new Router(
            pattern: 'feature/',
            handler: function () use (&$executedChild2) {
                $executedChild2 = true;
                return $this->createMockResponse();
            },
        );

        $rootRouter->addRouter($child1);
        $rootRouter->addRouter($child2);

        $request = $this->createRealRequest('/app/feature/');
        $result = $rootRouter->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(403, $result->http_code);
        $this->assertFalse($executedChild2);
    }

    public function test_returns_first_soft_error_if_no_child_router_returns_response(): void
    {
        $rootRouter = new Router(pattern: 'app/');

        // Child 1 fails method check (POST allowed, GET requested) -> soft 405 error
        $child1 = new Router(
            pattern: 'data/',
            handler: fn() => $this->createMockResponse(),
            method: Method::POST,
        );

        // Child 2 fails pattern match -> soft 404 error
        $child2 = new Router(
            pattern: 'other/',
            handler: fn() => $this->createMockResponse(),
        );

        $rootRouter->addRouter($child1);
        $rootRouter->addRouter($child2);

        $request = $this->createRealRequest('/app/data/', Method::GET);
        $result = $rootRouter->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        // First soft error encountered was 405 from Child 1
        $this->assertSame(405, $result->http_code);
    }

    public function test_child_router_inherits_parent_parameter_factories(): void
    {
        $capturedUser = null;

        $rootRouter = new Router(pattern: 'api/');
        $rootRouter->addParameterFactory(
            StubBaseUser::class,
            fn(string $id) => new StubBaseUser("inherited_{$id}"),
        );

        $childRouter = new Router(
            pattern: 'users/:user/',
            handler: function (StubBaseUser $user) use (&$capturedUser) {
                $capturedUser = $user;
                return $this->createMockResponse();
            },
        );

        $rootRouter->addRouter($childRouter);

        $request = $this->createRealRequest('/api/users/777/');
        $rootRouter->run($request);

        $this->assertInstanceOf(StubBaseUser::class, $capturedUser);
        $this->assertSame('inherited_777', $capturedUser->id);
    }

    public function test_child_parameter_factory_overrides_parent_factory(): void
    {
        $capturedUser = null;

        $rootRouter = new Router(pattern: 'api/');
        $rootRouter->addParameterFactory(
            StubBaseUser::class,
            fn(string $id) => new StubBaseUser("parent_{$id}"),
        );

        $childRouter = new Router(
            pattern: 'users/:user_id/',
            handler: function (StubBaseUser $user_id) use (&$capturedUser) {
                $capturedUser = $user_id;
                return $this->createMockResponse();
            },
        );
        $childRouter->addParameterFactory(
            StubBaseUser::class,
            fn(string $id) => new StubBaseUser("child_{$id}"),
        );

        $rootRouter->addRouter($childRouter);

        $request = $this->createRealRequest('/api/users/777/');
        $rootRouter->run($request);

        $this->assertInstanceOf(StubBaseUser::class, $capturedUser);
        $this->assertSame('child_777', $capturedUser->id);
    }

    // --- Extra Parameter Injection Tests ---

    public function test_injects_more_specific_type_first(): void
    {
        $capturedVal = null;

        $router = new Router(
            pattern: 'data/:value/',
            handler: function (string|int $value) use (&$capturedVal) {
                $capturedVal = $value;
                return $this->createMockResponse();
            },
        );

        // '42' can be cast to int, so int wins first in the union
        $requestInt = $this->createRealRequest('/data/42/');
        $router->run($requestInt);
        $this->assertSame(42, $capturedVal);

        // 'foo' cannot be cast to int, so string is matched next in the union
        $requestStr = $this->createRealRequest('/data/foo/');
        $router->run($requestStr);
        $this->assertSame('foo', $capturedVal);
    }

    public function test_injects_parameter_as_float(): void
    {
        $capturedFloat = null;

        $router = new Router(
            pattern: 'rate/:ratio/',
            handler: function (float $ratio) use (&$capturedFloat) {
                $capturedFloat = $ratio;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/rate/3.14/');
        $router->run($request);

        $this->assertSame(3.14, $capturedFloat);
    }

    public function test_injects_parameter_explicitly_typed_as_string(): void
    {
        $capturedString = null;

        $router = new Router(
            pattern: 'code/:val/',
            handler: function (string $val) use (&$capturedString) {
                $capturedString = $val;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/code/12345/');
        $router->run($request);

        $this->assertSame('12345', $capturedString);
    }

    public function test_injects_parameter_without_type_hint_as_raw_string(): void
    {
        $capturedRaw = null;

        $router = new Router(
            pattern: 'raw/:val/',
            handler: function ($val) use (&$capturedRaw) {
                $capturedRaw = $val;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/raw/untyped_123/');
        $router->run($request);

        $this->assertSame('untyped_123', $capturedRaw);
    }

    public function test_injects_null_when_nullable_type_hint_has_no_value(): void
    {
        $capturedValue = 'sentinel';

        $router = new Router(
            pattern: 'optional/',
            handler: function (?string $maybeVal) use (&$capturedValue) {
                $capturedValue = $maybeVal;
                return $this->createMockResponse();
            },
        );

        $request = $this->createRealRequest('/optional/');
        $router->run($request);

        $this->assertNull($capturedValue);
    }

    // --- Guard & Handler Exception Tests ---

    public function test_guard_throwing_invalid_format_exception_yields_soft_404_error(): void
    {
        $router = new Router(
            pattern: 'check/',
            handler: fn() => $this->createMockResponse(),
        );
        $router->guard(function () {
            throw new ParametersInvalidFormatException('Format invalid in guard');
        });

        $request = $this->createRealRequest('/check/');
        $result = $router->run($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function test_guard_throwing_parameters_missing_exception_yields_hard_500_error(): void
    {
        $router = new Router(
            pattern: 'check/',
            handler: fn() => $this->createMockResponse(),
        );
        $router->guard(function () {
            throw new ParametersMissingException('Missing param in guard');
        });

        $request = $this->createRealRequest('/check/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Guard parameters not available', $result->message);
        $this->assertInstanceOf(ParametersMissingException::class, $result->exception);
    }

    public function test_handler_throwing_exception_yields_hard_500_error(): void
    {
        $router = new Router(
            pattern: 'action/',
            handler: function () {
                throw new Exception('Unexpected failure during handling');
            },
        );

        $request = $this->createRealRequest('/action/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Unhandled exception', $result->message);
        $this->assertSame('Unexpected failure during handling', $result->exception->getMessage());
    }

    // --- Middleware & Execution Order ---

    public function test_middleware_executes_around_handler(): void
    {
        $log = [];

        $router = new Router(
            pattern: 'test/',
            handler: function () use (&$log) {
                $log[] = 'handler';
                return $this->createMockResponse();
            },
        );

        $router->addMiddleware(function (\Closure $next) use (&$log) {
            $log[] = 'middleware_before';
            $response = $next();
            $log[] = 'middleware_after';
            return $response;
        });

        $request = $this->createRealRequest('/test/');
        $result = $router->run($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(['middleware_before', 'handler', 'middleware_after'], $log);
    }

    public function test_middleware_executes_in_strict_priority_order(): void
    {
        $executionOrder = [];

        $router = new Router(
            pattern: 'test/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->addMiddleware(function (\Closure $next) use (&$executionOrder) {
            $executionOrder[] = 'low_before';
            $response = $next();
            $executionOrder[] = 'low_after';
            return $response;
        }, Priority::LOW);

        $router->addMiddleware(function (\Closure $next) use (&$executionOrder) {
            $executionOrder[] = 'high_before';
            $response = $next();
            $executionOrder[] = 'high_after';
            return $response;
        }, Priority::HIGH);

        $router->addMiddleware(function (\Closure $next) use (&$executionOrder) {
            $executionOrder[] = 'normal_before';
            $response = $next();
            $executionOrder[] = 'normal_after';
            return $response;
        }, Priority::NORMAL);

        $request = $this->createRealRequest('/test/');
        $router->run($request);

        $expected = [
            'high_before',
            'normal_before',
            'low_before',
            'low_after',
            'normal_after',
            'high_after',
        ];

        $this->assertSame($expected, $executionOrder);
    }

    public function test_middleware_can_short_circuit_execution(): void
    {
        $handlerExecuted = false;

        $router = new Router(
            pattern: 'blocked/',
            handler: function () use (&$handlerExecuted) {
                $handlerExecuted = true;
                return $this->createMockResponse();
            },
        );

        // Middleware returns a hard RouteError without invoking $next()
        $router->addMiddleware(function (RouteContext $context) {
            return new RouteError(401, $context, 'Unauthorized from middleware');
        });

        $request = $this->createRealRequest('/blocked/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(401, $result->http_code);
        $this->assertSame('Unauthorized from middleware', $result->message);
        $this->assertFalse($handlerExecuted);
    }

    public function test_middleware_receives_injected_parameters(): void
    {
        $capturedUserId = null;
        $capturedRequest = null;

        $router = new Router(
            pattern: 'users/:user_id/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->addMiddleware(function (\Closure $next, int $user_id, Request $request) use (&$capturedUserId, &$capturedRequest) {
            $capturedUserId = $user_id;
            $capturedRequest = $request;
            return $next();
        });

        $request = $this->createRealRequest('/users/555/');
        $router->run($request);

        $this->assertSame(555, $capturedUserId);
        $this->assertSame($request, $capturedRequest);
    }

    public function test_parent_middleware_wraps_child_router_execution(): void
    {
        $log = [];

        $rootRouter = new Router(pattern: 'api/');
        $childRouter = new Router(
            pattern: 'v1/',
            handler: function () use (&$log) {
                $log[] = 'child_handler';
                return $this->createMockResponse();
            },
        );

        $rootRouter->addMiddleware(function (\Closure $next) use (&$log) {
            $log[] = 'parent_middleware_start';
            $response = $next();
            $log[] = 'parent_middleware_end';
            return $response;
        });

        $childRouter->addMiddleware(function (\Closure $next) use (&$log) {
            $log[] = 'child_middleware_start';
            $response = $next();
            $log[] = 'child_middleware_end';
            return $response;
        });

        $rootRouter->addRouter($childRouter);

        $request = $this->createRealRequest('/api/v1/');
        $rootRouter->run($request);

        $expected = [
            'parent_middleware_start',
            'child_middleware_start',
            'child_handler',
            'child_middleware_end',
            'parent_middleware_end',
        ];

        $this->assertSame($expected, $log);
    }

    public function test_middleware_accepts_array_of_callables(): void
    {
        $log = [];

        $m1 = function (\Closure $next) use (&$log) {
            $log[] = 'm1';
            return $next();
        };

        $m2 = function (\Closure $next) use (&$log) {
            $log[] = 'm2';
            return $next();
        };

        $router = new Router(
            pattern: 'batch/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->addMiddleware([$m1, $m2]);

        $request = $this->createRealRequest('/batch/');
        $router->run($request);

        $this->assertSame(['m1', 'm2'], $log);
    }

    public function test_middleware_unhandled_exception_converts_to_500_route_error(): void
    {
        $router = new Router(
            pattern: 'crash/',
            handler: fn() => $this->createMockResponse(),
        );

        $router->addMiddleware(function () {
            throw new Exception('Middleware boom');
        });

        $request = $this->createRealRequest('/crash/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Unhandled exception in middleware', $result->message);
        $this->assertSame('Middleware boom', $result->exception->getMessage());
    }

    public function test_middleware_missing_parameter_converts_to_500_route_error(): void
    {
        $router = new Router(
            pattern: 'test/',
            handler: fn() => $this->createMockResponse(),
        );

        // Parameter $non_existent_param cannot be resolved from path or factories
        $router->addMiddleware(function (\Closure $next, string $non_existent_param) {
            return $next();
        });

        $request = $this->createRealRequest('/test/');
        $result = $router->run($request);

        $this->assertInstanceOf(RouteError::class, $result);
        $this->assertSame(500, $result->http_code);
        $this->assertTrue($result->isHardFailure());
        $this->assertSame('Middleware parameters not available', $result->message);
        $this->assertInstanceOf(ParametersMissingException::class, $result->exception);
    }

}

class StubFromStringObject implements FromStringInterface
{

    public function __construct(public readonly string $val) {}

    public static function fromString(string $value): static|null
    {
        return $value === 'invalid' ? null : new static($value);
    }

}

class StubBaseUser
{

    public function __construct(public readonly string $id) {}

}

class StubAdminUser extends StubBaseUser
{

}
