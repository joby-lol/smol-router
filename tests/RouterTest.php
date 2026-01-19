<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Request\Cookies\Cookies;
use Joby\Smol\Request\Headers\Headers;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Post\Post;
use Joby\Smol\Request\Request;
use Joby\Smol\Request\Source\Source;
use Joby\Smol\Response\Response;
use Joby\Smol\Response\Status;
use Joby\Smol\Router\Matchers\CatchallMatcher;
use Joby\Smol\Router\Matchers\ExactMatcher;
use Joby\Smol\Router\Matchers\PatternMatcher;
use Joby\Smol\Router\Matchers\PrefixMatcher;
use Joby\Smol\Router\Matchers\SuffixMatcher;
use Joby\Smol\URL\Path;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RouterTest extends TestCase
{

    public function test_matches_exact_route(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('about'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/about');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_matches_pattern_route_with_parameters(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn(string $id) => $this->createJsonResponse(['id' => $id])
        );

        $request = $this->createRequest('/users/123');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_injects_typed_parameters(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn(int $id) => $this->createJsonResponse(['id' => $id, 'type' => gettype($id)])
        );

        $request = $this->createRequest('/users/123');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_injects_path_parameter(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn(string $path, int $id) => $this->createJsonResponse(['path' => $path, 'id' => $id])
        );

        $request = $this->createRequest('/users/123');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_injects_request_parameter(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('test'),
            fn(Request $request) => $this->createJsonResponse(['method' => $request->method->value])
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_filters_by_http_method(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('api'),
            fn() => new Response(new Status(200)),
            Method::GET,
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);

        $this->assertEquals(200, $router->run($getRequest)->status->code);
        $this->assertEquals(404, $router->run($postRequest)->status->code);
    }

    public function test_supports_multiple_http_methods(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('api'),
            fn() => new Response(new Status(200)),
            [Method::GET, Method::POST],
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);
        $putRequest = $this->createRequest('/api', Method::PUT);

        $this->assertEquals(200, $router->run($getRequest)->status->code);
        $this->assertEquals(200, $router->run($postRequest)->status->code);
        $this->assertEquals(404, $router->run($putRequest)->status->code);
    }

    public function test_respects_route_priority_high_first(): void
    {
        $router = new Router();
        $router->add(
            new CatchallMatcher(),
            fn() => $this->createTextResponse('low'),
            priority: Priority::LOW,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => $this->createTextResponse('high'),
            priority: Priority::HIGH,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => $this->createTextResponse('normal'),
            priority: Priority::NORMAL,
        );

        $request = $this->createRequest('/anything');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('high', $content);
    }

    public function test_returns_404_when_no_routes_match(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('about'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/contact');
        $response = $router->run($request);

        $this->assertEquals(404, $response->status->code);
    }

    public function test_normalizes_trailing_slashes(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('about'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/about/');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_preserves_root_as_empty_string(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher(''),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_custom_route_extractor(): void
    {
        $router = new Router();
        $router->routeExtractor(fn(Request $r) => 'custom');
        $router->add(
            new ExactMatcher('custom'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/anything');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_custom_route_normalizer(): void
    {
        $router = new Router();
        $router->routeNormalizer(fn(string $route) => strtolower($route));
        $router->add(
            new ExactMatcher('about'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/ABOUT');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_handler_with_default_parameter_value(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('test'),
            fn(string $missing = 'default') => $this->createTextResponse($missing)
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_handler_with_nullable_parameter(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('test'),
            fn(?string $missing) => $this->createTextResponse($missing ?? 'null')
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_handler_returning_null_falls_through(): void
    {
        $router = new Router();
        $router->add(
            new CatchallMatcher(),
            fn() => null,
            priority: Priority::HIGH,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => $this->createTextResponse('fallback')
        );

        $request = $this->createRequest('/anything');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('fallback', $content);
    }

    public function test_throws_invalid_parameter_exception_for_missing_required_parameter(): void
    {
        $router = new Router();
        $router->add(
            new ExactMatcher('test'),
            fn(string $required) => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertEquals(400, $response->status->code);
    }

    public function test_custom_type_handler(): void
    {
        $router = new Router();
        $router->typeHandler('custom', fn(string $v) => strtoupper($v));
        $router->add(
            new PatternMatcher('test/:value'),
            function (string $value) {
                return $this->createTextResponse($value);
            }
        );

        $request = $this->createRequest('/test/hello');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_type_handler_int_conversion(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn(int $id) => $this->createTextResponse(gettype($id))
        );

        $request = $this->createRequest('/users/123');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('integer', $content);
    }

    public function test_type_handler_float_conversion(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('price/:amount'),
            fn(float $amount) => $this->createTextResponse(gettype($amount))
        );

        $request = $this->createRequest('/price/19.99');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('double', $content);
    }

    public function test_type_handler_bool_conversion(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('enabled/:flag'),
            fn(bool $flag) => $this->createTextResponse($flag ? 'true' : 'false')
        );

        $request = $this->createRequest('/enabled/yes');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('true', $content);
    }

    public function test_custom_exception_handler(): void
    {
        $router = new Router();
        $router->exceptionClassHandler(
            RuntimeException::class,
            fn(RuntimeException $e) => new HttpException(503, 'Service Unavailable')
        );
        $router->add(
            new ExactMatcher('error'),
            function () {
                throw new RuntimeException('Test error');
            }
        );

        $request = $this->createRequest('/error');
        $response = $router->run($request);

        $this->assertEquals(503, $response->status->code);
    }

    public function test_custom_error_page_builder_for_404(): void
    {
        $router = new Router();
        $router->addErrorResponseBuilder('404', function (HttpException $exception) {
            return new Response($exception->status, 'Custom 404');
        });

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(404, $response->status->code);
        $this->assertStringContainsString('Custom 404', $content);
    }

    public function test_error_page_builder_wildcard_4xx(): void
    {
        $router = new Router();
        $router->addErrorResponseBuilder('4xx', function (HttpException $exception) {
            return new Response($exception->status, '4xx error');
        });

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(404, $response->status->code);
        $this->assertStringContainsString('4xx error', $content);
    }

    public function test_error_page_builder_default(): void
    {
        $router = new Router();
        $router->addErrorResponseBuilder('default', function (HttpException $exception) {
            return new Response($exception->status, 'Default error');
        });

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(404, $response->status->code);
        $this->assertStringContainsString('Default error', $content);
    }

    public function test_prefix_matcher_integration(): void
    {
        $router = new Router();
        $router->add(
            new PrefixMatcher('api/', 'path'),
            fn(string $path) => $this->createTextResponse("Path: $path")
        );

        $request = $this->createRequest('/api/users/123');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(200, $response->status->code);
        $this->assertStringContainsString('users/123', $content);
    }

    public function test_suffix_matcher_integration(): void
    {
        $router = new Router();
        $router->add(
            new SuffixMatcher('.json', 'path'),
            fn(string $path) => $this->createTextResponse("Path: $path")
        );

        $request = $this->createRequest('/users/123.json');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(200, $response->status->code);
        $this->assertStringContainsString('users/123', $content);
    }

    public function test_multiple_routes_with_priority(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn() => $this->createTextResponse('pattern'),
            priority: Priority::NORMAL,
        );
        $router->add(
            new ExactMatcher('users/special'),
            fn() => $this->createTextResponse('exact'),
            priority: Priority::HIGH,
        );

        $normalRequest = $this->createRequest('/users/123');
        $specialRequest = $this->createRequest('/users/special');

        $normalResponse = $router->run($normalRequest);
        $specialResponse = $router->run($specialRequest);

        $this->assertStringContainsString('pattern', $normalResponse->content->content);
        $this->assertStringContainsString('exact', $specialResponse->content->content);
    }

    public function test_invalid_type_conversion_returns_400(): void
    {
        $router = new Router();
        $router->add(
            new PatternMatcher('users/:id'),
            fn(int $id) => new Response(new Status(200))
        );

        $request = $this->createRequest('/users/not-a-number');
        $response = $router->run($request);

        $this->assertEquals(400, $response->status->code);
    }

    public function test_remove_type_handler(): void
    {
        $router = new Router();
        $router->typeHandler('int', null);
        $router->add(
            new PatternMatcher('users/:id'),
            fn(int $id) => new Response(new Status(200))
        );

        $request = $this->createRequest('/users/123');
        $response = $router->run($request);

        // Without int type handler, conversion should fail
        $this->assertEquals(400, $response->status->code);
    }

    public function test_remove_exception_handler(): void
    {
        $router = new Router();
        $router->exceptionClassHandler(HttpException::class, null);
        $router->add(
            new ExactMatcher('error'),
            function () {
                throw new HttpException(418);
            }
        );

        $request = $this->createRequest('/error');
        $response = $router->run($request);

        // Should fall back to default handler (500)
        $this->assertEquals(500, $response->status->code);
    }

    public function test_extract_route_with_custom_extractor(): void
    {
        $router = new Router();
        $router->routeExtractor(fn(Request $r) => 'extracted');

        $request = $this->createRequest('/anything');
        $extracted = $router->extractRoute($request);

        $this->assertEquals('extracted', $extracted);
    }

    public function test_normalize_route_strips_leading_and_trailing_slashes(): void
    {
        $router = new Router();

        $this->assertEquals('about', $router->normalizeRoute('/about/'));
        $this->assertEquals('', $router->normalizeRoute('/'));
    }

    public function test_normalize_route_with_custom_normalizer(): void
    {
        $router = new Router();
        $router->routeNormalizer(fn(string $route) => strtoupper($route));

        $normalized = $router->normalizeRoute('/about/');

        $this->assertEquals('ABOUT', $normalized);
    }

    private function createRequest(string $path, Method $method = Method::GET): Request
    {
        $url = new URL(Path::fromString($path));

        return new Request(
            $url,
            $method,
            $this->createStub(Headers::class),
            $this->createStub(Cookies::class),
            $this->createStub(Post::class),
            $this->createStub(Source::class),
        );
    }

    private function createJsonResponse(array $data): Response
    {
        return new Response(new Status(200));
    }

    private function createTextResponse(string $text): Response
    {
        $response = new Response(new Status(200));
        $response->setContent(new \Joby\Smol\Response\Content\StringContent($text));
        return $response;
    }

    // Guard tests

    public function test_guard_allows_access_when_returning_null(): void
    {
        $router = new Router();
        $router->guard(
            new ExactMatcher('protected'),
            fn() => null
        );
        $router->add(
            new ExactMatcher('protected'),
            fn() => $this->createTextResponse('allowed')
        );

        $request = $this->createRequest('/protected');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
        $this->assertStringContainsString('allowed', $response->content->content);
    }

    public function test_guard_blocks_access_when_returning_false(): void
    {
        $router = new Router();
        $router->guard(
            new ExactMatcher('protected'),
            fn() => false
        );
        $router->add(
            new ExactMatcher('protected'),
            fn() => $this->createTextResponse('should not see this')
        );

        $request = $this->createRequest('/protected');
        $response = $router->run($request);

        $this->assertEquals(403, $response->status->code);
    }

    public function test_guard_allows_access_when_returning_true(): void
    {
        $router = new Router();
        $router->guard(
            new ExactMatcher('protected'),
            fn() => true
        );
        $router->add(
            new ExactMatcher('protected'),
            fn() => $this->createTextResponse('allowed')
        );

        $request = $this->createRequest('/protected');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
        $this->assertStringContainsString('allowed', $response->content->content);
    }

    public function test_guard_injects_path_parameter(): void
    {
        $router = new Router();
        $capturedPath = null;
        $router->guard(
            new ExactMatcher('test'),
            function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals('test', $capturedPath);
    }

    public function test_guard_injects_request_parameter(): void
    {
        $router = new Router();
        $capturedMethod = null;
        $router->guard(
            new ExactMatcher('test'),
            function (Request $request) use (&$capturedMethod) {
                $capturedMethod = $request->method->value;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals('GET', $capturedMethod);
    }

    public function test_guard_injects_matched_parameters(): void
    {
        $router = new Router();
        $capturedId = null;
        $router->guard(
            new PatternMatcher('users/:id'),
            function (string $id) use (&$capturedId) {
                $capturedId = $id;
                return null;
            }
        );
        $router->add(
            new PatternMatcher('users/:id'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/users/123');
        $router->run($request);

        $this->assertEquals('123', $capturedId);
    }

    public function test_guard_filters_by_http_method(): void
    {
        $router = new Router();
        $router->guard(
            new ExactMatcher('api'),
            fn() => false,
            Method::POST,
        );
        $router->add(
            new ExactMatcher('api'),
            fn() => $this->createTextResponse('success')
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);

        $this->assertEquals(200, $router->run($getRequest)->status->code);
        $this->assertEquals(403, $router->run($postRequest)->status->code);
    }

    public function test_guard_supports_multiple_http_methods(): void
    {
        $router = new Router();
        $router->guard(
            new ExactMatcher('api'),
            fn() => false,
            [Method::POST, Method::PUT],
        );
        $router->add(
            new ExactMatcher('api'),
            fn() => $this->createTextResponse('success')
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);
        $putRequest = $this->createRequest('/api', Method::PUT);

        $this->assertEquals(200, $router->run($getRequest)->status->code);
        $this->assertEquals(403, $router->run($postRequest)->status->code);
        $this->assertEquals(403, $router->run($putRequest)->status->code);
    }

    public function test_guard_respects_priority(): void
    {
        $router = new Router();
        $executionOrder = [];

        $router->guard(
            new CatchallMatcher(),
            function () use (&$executionOrder) {
                $executionOrder[] = 'low';
                return null;
            },
            priority: Priority::LOW,
        );
        $router->guard(
            new CatchallMatcher(),
            function () use (&$executionOrder) {
                $executionOrder[] = 'high';
                return null;
            },
            priority: Priority::HIGH,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals(['high', 'low'], $executionOrder);
    }

    public function test_guard_stops_processing_on_false(): void
    {
        $router = new Router();
        $secondGuardCalled = false;

        $router->guard(
            new CatchallMatcher(),
            fn() => false,
            priority: Priority::HIGH,
        );
        $router->guard(
            new CatchallMatcher(),
            function () use (&$secondGuardCalled) {
                $secondGuardCalled = true;
                return null;
            },
            priority: Priority::LOW,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertFalse($secondGuardCalled);
    }

    public function test_guard_stops_processing_on_true(): void
    {
        $router = new Router();
        $secondGuardCalled = false;

        $router->guard(
            new CatchallMatcher(),
            fn() => true,
            priority: Priority::HIGH,
        );
        $router->guard(
            new CatchallMatcher(),
            function () use (&$secondGuardCalled) {
                $secondGuardCalled = true;
                return null;
            },
            priority: Priority::LOW,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertFalse($secondGuardCalled);
    }

    public function test_guard_only_runs_on_matching_routes(): void
    {
        $router = new Router();
        $guardCalled = false;

        $router->guard(
            new ExactMatcher('admin'),
            function () use (&$guardCalled) {
                $guardCalled = true;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('public'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/public');
        $router->run($request);

        $this->assertFalse($guardCalled);
    }

    // modifier tests

    public function test_modify_receives_response(): void
    {
        $router = new Router();
        $capturedStatus = null;

        $router->modify(
            new ExactMatcher('test'),
            function (Response $response) use (&$capturedStatus) {
                $capturedStatus = $response->status->code;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => new Response(new Status(201))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals(201, $capturedStatus);
    }

    public function test_modify_keeps_original_response_when_returning_null(): void
    {
        $router = new Router();
        $router->modify(
            new ExactMatcher('test'),
            fn(Response $response) => null
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => $this->createTextResponse('original')
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertStringContainsString('original', $response->content->content);
    }

    public function test_modify_replaces_response_when_returning_response(): void
    {
        $router = new Router();
        $router->modify(
            new ExactMatcher('test'),
            fn(Response $response) => $this->createTextResponse('modifyed')
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => $this->createTextResponse('original')
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertStringContainsString('modifyed', $response->content->content);
        $this->assertStringNotContainsString('original', $response->content->content);
    }

    public function test_modify_injects_path_parameter(): void
    {
        $router = new Router();
        $capturedPath = null;

        $router->modify(
            new ExactMatcher('test'),
            function (string $path, Response $response) use (&$capturedPath) {
                $capturedPath = $path;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals('test', $capturedPath);
    }

    public function test_modify_injects_request_parameter(): void
    {
        $router = new Router();
        $capturedMethod = null;

        $router->modify(
            new ExactMatcher('test'),
            function (Request $request, Response $response) use (&$capturedMethod) {
                $capturedMethod = $request->method->value;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('test'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals('GET', $capturedMethod);
    }

    public function test_modify_injects_matched_parameters(): void
    {
        $router = new Router();
        $capturedId = null;

        $router->modify(
            new PatternMatcher('users/:id'),
            function (string $id, Response $response) use (&$capturedId) {
                $capturedId = $id;
                return null;
            }
        );
        $router->add(
            new PatternMatcher('users/:id'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/users/456');
        $router->run($request);

        $this->assertEquals('456', $capturedId);
    }

    public function test_modify_filters_by_http_method(): void
    {
        $router = new Router();
        $modifierCalled = false;

        $router->modify(
            new ExactMatcher('api'),
            function (Response $response) use (&$modifierCalled) {
                $modifierCalled = true;
                return null;
            },
            Method::POST,
        );
        $router->add(
            new ExactMatcher('api'),
            fn() => new Response(new Status(200))
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);

        $modifierCalled = false;
        $router->run($getRequest);
        $this->assertFalse($modifierCalled);

        $modifierCalled = false;
        $router->run($postRequest);
        $this->assertTrue($modifierCalled);
    }

    public function test_modify_supports_multiple_http_methods(): void
    {
        $router = new Router();
        $callCount = 0;

        $router->modify(
            new ExactMatcher('api'),
            function (Response $response) use (&$callCount) {
                $callCount++;
                return null;
            },
            [Method::POST, Method::PUT],
        );
        $router->add(
            new ExactMatcher('api'),
            fn() => new Response(new Status(200))
        );

        $router->run($this->createRequest('/api', Method::GET));
        $router->run($this->createRequest('/api', Method::POST));
        $router->run($this->createRequest('/api', Method::PUT));

        $this->assertEquals(2, $callCount);
    }

    public function test_modify_respects_priority(): void
    {
        $router = new Router();
        $executionOrder = [];

        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$executionOrder) {
                $executionOrder[] = 'low';
                return null;
            },
            priority: Priority::LOW,
        );
        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$executionOrder) {
                $executionOrder[] = 'high';
                return null;
            },
            priority: Priority::HIGH,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertEquals(['high', 'low'], $executionOrder);
    }

    public function test_modify_chains_multiple_modifiers(): void
    {
        $router = new Router();

        $router->modify(
            new CatchallMatcher(),
            function (Response $response) {
                return $this->createTextResponse('first');
            },
            priority: Priority::HIGH,
        );
        $router->modify(
            new CatchallMatcher(),
            function (Response $response) {
                $content = $response->content->content;
                return $this->createTextResponse($content . ' second');
            },
            priority: Priority::LOW,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => $this->createTextResponse('original')
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertStringContainsString('first second', $response->content->content);
    }

    public function test_modify_only_runs_on_matching_routes(): void
    {
        $router = new Router();
        $modifierCalled = false;

        $router->modify(
            new ExactMatcher('api'),
            function (Response $response) use (&$modifierCalled) {
                $modifierCalled = true;
                return null;
            }
        );
        $router->add(
            new ExactMatcher('public'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/public');
        $router->run($request);

        $this->assertFalse($modifierCalled);
    }

    public function test_modify_runs_on_404_response(): void
    {
        $router = new Router();
        $capturedStatus = null;

        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$capturedStatus) {
                $capturedStatus = $response->status->code;
                return null;
            }
        );

        $request = $this->createRequest('/nonexistent');
        $router->run($request);

        $this->assertEquals(404, $capturedStatus);
    }

    public function test_modify_runs_when_guard_blocks(): void
    {
        $router = new Router();
        $modifierCalled = false;

        $router->guard(
            new CatchallMatcher(),
            fn() => false
        );
        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$modifierCalled) {
                $modifierCalled = true;
                return null;
            }
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertTrue($modifierCalled);
    }

    public function test_handler_returning_final_response_skips_modifiers(): void
    {
        $router = new Router();
        $modifierCalled = false;

        $finalResponse = $this->createMock(FinalResponse::class);

        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$modifierCalled) {
                $modifierCalled = true;
                return null;
            }
        );
        $router->add(
            new CatchallMatcher(),
            fn() => $finalResponse
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertFalse($modifierCalled);
    }

    public function test_modifier_returning_final_response_skips_further_modifiers(): void
    {
        $router = new Router();
        $secondmodifierCalled = false;

        $finalResponse = $this->createMock(FinalResponse::class);

        $router->modify(
            new CatchallMatcher(),
            fn(Response $response) => $finalResponse,
            priority: Priority::HIGH,
        );
        $router->modify(
            new CatchallMatcher(),
            function (Response $response) use (&$secondmodifierCalled) {
                $secondmodifierCalled = true;
                return null;
            },
            priority: Priority::LOW,
        );
        $router->add(
            new CatchallMatcher(),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');
        $router->run($request);

        $this->assertFalse($secondmodifierCalled);
    }

    public function test_error_page_builder_with_matcher(): void
    {
        $router = new Router();
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'API 404'),
            new PrefixMatcher('api/'),
        );
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'Web 404')
        );

        $apiRequest = $this->createRequest('/api/missing');
        $webRequest = $this->createRequest('/web/missing');

        $apiResponse = $router->run($apiRequest);
        $webResponse = $router->run($webRequest);

        $this->assertStringContainsString('API 404', $apiResponse->content->content);
        $this->assertStringContainsString('Web 404', $webResponse->content->content);
    }

    public function test_error_page_builder_matcher_with_parameters(): void
    {
        $router = new Router();
        $capturedVersion = null;

        $router->addErrorResponseBuilder(
            '404',
            function (string $version, HttpException $exception) use (&$capturedVersion) {
                $capturedVersion = $version;
                return new Response($exception->status, "API {$version} not found");
            },
            new PatternMatcher('api/:version/:endpoint'),
        );

        $request = $this->createRequest('/api/v2/missing');
        $response = $router->run($request);

        $this->assertEquals('v2', $capturedVersion);
        $this->assertStringContainsString('API v2 not found', $response->content->content);
    }

    public function test_error_page_builder_respects_priority(): void
    {
        $router = new Router();

        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'low priority'),
            priority: Priority::LOW,
        );
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'high priority'),
            priority: Priority::HIGH,
        );

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $this->assertStringContainsString('high priority', $response->content->content);
    }

    public function test_error_page_builder_null_fallback(): void
    {
        $router = new Router();

        // First builder returns null
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => null,
            priority: Priority::HIGH,
        );
        // Second builder handles it
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'fallback'),
            priority: Priority::LOW,
        );

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $this->assertStringContainsString('fallback', $response->content->content);
    }

    public function test_error_page_builder_matcher_only_applies_to_matching_paths(): void
    {
        $router = new Router();
        $apiBuilderCalled = false;

        $router->addErrorResponseBuilder(
            '404',
            function (HttpException $exception) use (&$apiBuilderCalled) {
                $apiBuilderCalled = true;
                return new Response($exception->status, 'API error');
            },
            new PrefixMatcher('api/'),
        );

        $request = $this->createRequest('/web/missing');
        $router->run($request);

        $this->assertFalse($apiBuilderCalled);
    }

    public function test_error_page_builder_specificity_then_priority(): void
    {
        $router = new Router();

        // More specific (404) with low priority
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'specific 404'),
            priority: Priority::LOW,
        );
        // Less specific (4xx) with high priority
        $router->addErrorResponseBuilder(
            '4xx',
            fn(HttpException $exception) => new Response($exception->status, 'generic 4xx'),
            priority: Priority::HIGH,
        );

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        // Specificity wins over priority
        $this->assertStringContainsString('specific 404', $response->content->content);
    }

    public function test_error_page_builder_combines_matcher_and_code_specificity(): void
    {
        $router = new Router();

        // Specific matcher with wildcard code
        $router->addErrorResponseBuilder(
            '4xx',
            fn(HttpException $exception) => new Response($exception->status, 'API 4xx'),
            new PrefixMatcher('api/'),
        );
        // Catchall matcher with specific code
        $router->addErrorResponseBuilder(
            '404',
            fn(HttpException $exception) => new Response($exception->status, 'general 404')
        );

        $apiRequest = $this->createRequest('/api/missing');
        $webRequest = $this->createRequest('/web/missing');

        $apiResponse = $router->run($apiRequest);
        $webResponse = $router->run($webRequest);

        // Code specificity takes precedence
        $this->assertStringContainsString('general 404', $apiResponse->content->content);
        $this->assertStringContainsString('general 404', $webResponse->content->content);
    }

}
