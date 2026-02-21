<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use InvalidArgumentException;
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
use stdClass;

class TinyRouterTest extends TestCase
{

    public function test_matches_exact_route(): void
    {
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher('api'),
            fn() => new Response(new Status(200)),
            Method::GET,
        );

        $getRequest = $this->createRequest('/api', Method::GET);
        $postRequest = $this->createRequest('/api', Method::POST);

        $this->assertEquals(200, $router->run($getRequest)->status->code);
        $this->assertNull($router->run($postRequest)->status->code);
    }

    public function test_supports_multiple_http_methods(): void
    {
        $router = new TinyRouter();
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
        $this->assertNull($router->run($putRequest)->status->code);
    }

    public function test_respects_route_priority_high_first(): void
    {
        $router = new TinyRouter();
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

    public function test_returns_null_when_no_routes_match(): void
    {
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher('about'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/contact');
        $this->assertNull($router->run($request));
    }

    public function test_normalizes_trailing_slashes(): void
    {
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher('about/'),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('/about/');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_preserves_root_as_empty_string(): void
    {
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher(''),
            fn() => new Response(new Status(200))
        );

        $request = $this->createRequest('');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
    }

    public function test_custom_route_extractor(): void
    {
        $router = new TinyRouter();
        $router->routeExtractor(fn(Request $r) => '/custom');
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher('test'),
            fn(string $required) => new Response(new Status(200))
        );

        $request = $this->createRequest('/test');

        $this->expectException(InvalidArgumentException::class);
        $router->run($request);
    }

    public function test_custom_type_handler(): void
    {
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
        $router->add(
            new PatternMatcher('enabled/:flag'),
            fn(bool $flag) => $this->createTextResponse($flag ? 'true' : 'false')
        );

        $request = $this->createRequest('/enabled/yes');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertStringContainsString('true', $content);
    }

    public function test_passes_through_exception(): void
    {
        $router = new TinyRouter();
        $router->add(
            new ExactMatcher('error'),
            function () {
                throw new RuntimeException('Test error');
            }
        );

        $request = $this->createRequest('/error');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test error');
        $router->run($request);
    }

    public function test_prefix_matcher_integration(): void
    {
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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
        $router = new TinyRouter();
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

    public function test_remove_type_handler(): void
    {
        $router = new TinyRouter();
        $router->typeHandler('int', null);
        $router->add(
            new PatternMatcher('users/:id'),
            fn(int $id) => new Response(new Status(200))
        );

        $request = $this->createRequest('/users/123');

        $this->expectException(InvalidParameterException::class);
        $router->run($request);
    }

    public function test_extract_route_with_custom_extractor(): void
    {
        $router = new TinyRouter();
        $router->routeExtractor(fn(Request $r) => 'extracted');

        $request = $this->createRequest('/anything');
        $extracted = $router->extractRoute($request);

        $this->assertEquals('extracted', $extracted);
    }

    public function test_normalize_route_strips_leading_and_trailing_slashes(): void
    {
        $router = new TinyRouter();

        $this->assertEquals('about', $router->normalizeRoute('about'));
        $this->assertEquals('', $router->normalizeRoute('/'));
    }

    public function test_normalize_route_with_custom_normalizer(): void
    {
        $router = new TinyRouter();
        $router->routeNormalizer(fn(string $route) => strtoupper($route));

        $normalized = $router->normalizeRoute('/about/');

        $this->assertEquals('ABOUT/', $normalized);
    }

    public function test_injects_mixed_parameters(): void
    {
        // custom matcher that returns a match with non-string parameters
        $matcher =

            new class implements MatcherInterface {

            public function match(string $path, Request $request): MatchedRoute|null
            {
                return new MatchedRoute($path, $request, ['int_param' => 5, 'obj_param' => new stdClass()]);
            }

            };

        $router = new TinyRouter();
        $router->add(
            $matcher,
            fn(int $int_param, object $obj_param) => $this->createTextResponse($int_param . get_class($obj_param))
        );

        $request = $this->createRequest('/test');
        $response = $router->run($request);

        $this->assertEquals(200, $response->status->code);
        $this->assertEquals('5stdClass', $response->content->content);

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

}
