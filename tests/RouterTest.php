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
        $router->errorPageBuilder('404', function (HttpException $e) {
            $response = new Response($e->status);
            $response->setContent(new \Joby\Smol\Response\Content\StringContent('Custom 404'));
            return $response;
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
        $router->errorPageBuilder('4xx', function (HttpException $e) {
            $response = new Response($e->status);
            $response->setContent(new \Joby\Smol\Response\Content\StringContent('4xx error'));
            return $response;
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
        $router->errorPageBuilder('default', function (HttpException $e) {
            $response = new Response($e->status);
            $response->setContent(new \Joby\Smol\Response\Content\StringContent('Default error'));
            return $response;
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

    public function test_remove_error_page_builder(): void
    {
        $router = new Router();
        $router->errorPageBuilder('404', fn() => $this->createTextResponse('Custom'));
        $router->errorPageBuilder('404', null);

        $request = $this->createRequest('/nonexistent');
        $response = $router->run($request);

        $content = $response->content->content;
        $this->assertEquals(404, $response->status->code);
        // Should use default error page
        $this->assertStringContainsString('Error 404', $content);
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

}
