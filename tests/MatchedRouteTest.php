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
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class MatchedRouteTest extends TestCase
{

    public function test_constructor_with_all_parameters(): void
    {
        $request = $this->createMockRequest();
        $parameters = ['id' => '123', 'action' => 'edit'];

        $matchedRoute = new MatchedRoute('/users/123/edit', $request, $parameters);

        $this->assertEquals('/users/123/edit', $matchedRoute->path);
        $this->assertSame($request, $matchedRoute->request);
        $this->assertEquals(['id' => '123', 'action' => 'edit'], $matchedRoute->parameters);
    }

    public function test_constructor_with_default_parameters(): void
    {
        $request = $this->createMockRequest();

        $matchedRoute = new MatchedRoute('/about', $request);

        $this->assertEquals('/about', $matchedRoute->path);
        $this->assertSame($request, $matchedRoute->request);
        $this->assertEquals([], $matchedRoute->parameters);
    }

    public function test_constructor_with_empty_parameters_array(): void
    {
        $request = $this->createMockRequest();

        $matchedRoute = new MatchedRoute('/contact', $request, []);

        $this->assertEquals('/contact', $matchedRoute->path);
        $this->assertSame($request, $matchedRoute->request);
        $this->assertEquals([], $matchedRoute->parameters);
    }

    public function test_has_parameter_returns_true_when_parameter_exists(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users/123', $request, ['id' => '123']);

        $this->assertTrue($matchedRoute->hasParameter('id'));
    }

    public function test_has_parameter_returns_false_when_parameter_does_not_exist(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users/123', $request, ['id' => '123']);

        $this->assertFalse($matchedRoute->hasParameter('name'));
    }

    public function test_has_parameter_returns_false_for_empty_parameters(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/about', $request, []);

        $this->assertFalse($matchedRoute->hasParameter('anything'));
    }

    public function test_has_parameter_returns_true_even_when_parameter_value_is_empty_string(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/path', $request, ['param' => '']);

        $this->assertTrue($matchedRoute->hasParameter('param'));
    }

    public function test_parameter_returns_value_when_parameter_exists(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users/123', $request, ['id' => '123']);

        $this->assertEquals('123', $matchedRoute->parameter('id'));
    }

    public function test_parameter_returns_null_when_parameter_does_not_exist(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users/123', $request, ['id' => '123']);

        $this->assertNull($matchedRoute->parameter('name'));
    }

    public function test_parameter_returns_null_for_empty_parameters(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/about', $request, []);

        $this->assertNull($matchedRoute->parameter('anything'));
    }

    public function test_parameter_returns_empty_string_when_parameter_value_is_empty_string(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/path', $request, ['param' => '']);

        $this->assertEquals('', $matchedRoute->parameter('param'));
    }

    public function test_parameter_with_multiple_parameters(): void
    {
        $request = $this->createMockRequest();
        $parameters = [
            'post_id' => '456',
            'comment_id' => '789',
            'action' => 'edit'
        ];
        $matchedRoute = new MatchedRoute('/posts/456/comments/789/edit', $request, $parameters);

        $this->assertEquals('456', $matchedRoute->parameter('post_id'));
        $this->assertEquals('789', $matchedRoute->parameter('comment_id'));
        $this->assertEquals('edit', $matchedRoute->parameter('action'));
        $this->assertNull($matchedRoute->parameter('nonexistent'));
    }

    public function test_parameter_with_alphanumeric_value(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/items/abc123xyz', $request, ['slug' => 'abc123xyz']);

        $this->assertEquals('abc123xyz', $matchedRoute->parameter('slug'));
    }

    public function test_parameter_with_special_characters(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users/john-doe', $request, ['slug' => 'john-doe']);

        $this->assertEquals('john-doe', $matchedRoute->parameter('slug'));
    }

    public function test_path_property(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/api/v1/users', $request);

        $this->assertEquals('/api/v1/users', $matchedRoute->path);
    }

    public function test_request_property(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/users', $request);

        $this->assertSame($request, $matchedRoute->request);
        $this->assertInstanceOf(Request::class, $matchedRoute->request);
    }

    public function test_parameters_property(): void
    {
        $request = $this->createMockRequest();
        $parameters = ['id' => '123', 'format' => 'json'];
        $matchedRoute = new MatchedRoute('/users/123.json', $request, $parameters);

        $this->assertEquals($parameters, $matchedRoute->parameters);
        $this->assertIsArray($matchedRoute->parameters);
        $this->assertCount(2, $matchedRoute->parameters);
    }

    public function test_empty_path(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('', $request);

        $this->assertEquals('', $matchedRoute->path);
    }

    public function test_root_path(): void
    {
        $request = $this->createMockRequest();
        $matchedRoute = new MatchedRoute('/', $request);

        $this->assertEquals('/', $matchedRoute->path);
    }

    private function createMockRequest(): Request
    {
        return new Request(
            $this->createStub(URL::class),
            Method::GET,
            $this->createStub(Headers::class),
            $this->createStub(Cookies::class),
            $this->createStub(Post::class),
            $this->createStub(Source::class),
        );
    }

}
