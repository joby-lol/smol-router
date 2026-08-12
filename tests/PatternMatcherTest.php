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
use Joby\Smol\URL\Path;
use Joby\Smol\URL\URL;
use PHPUnit\Framework\TestCase;

class PatternMatcherTest extends TestCase
{

    private function createRequest(string $path): Request
    {
        return new Request(
            url: new URL(Path::fromString($path)),
            method: Method::GET,
            headers: new Headers(null, null, null, null, []),
            cookies: new Cookies([]),
            post: new Post([], []),
            source: new Source('127.0.0.1', '127.0.0.1', 'test ua'),
        );
    }

    private function createContext(string $path, ?Router $router = null): RouteContext
    {
        $router ??= $this->createMock(Router::class);
        return RouteContext::fromRequest($this->createRequest($path), $router);
    }

    public function test_matches_literal_prefix(): void
    {
        $matcher = new PatternMatcher('api/v1/');
        $router = $this->createMock(Router::class);
        $context = $this->createContext('/api/v1/users', $router);

        $result = $matcher->match($context, $router);

        $this->assertNotNull($result);
        $this->assertSame('users', $result->remaining_path);
        $this->assertSame([], $result->parameters);
        $this->assertSame($router, $result->router);
    }

    public function test_matches_and_extracts_named_parameters(): void
    {
        $matcher = new PatternMatcher('users/:id/posts/:post_id/');
        $router = $this->createMock(Router::class);
        $context = $this->createContext('/users/42/posts/101/comments', $router);

        $result = $matcher->match($context, $router);

        $this->assertNotNull($result);
        $this->assertSame('comments', $result->remaining_path);
        $this->assertSame(
            [
                'id'      => '42',
                'post_id' => '101',
            ],
            $result->parameters,
        );
        $this->assertSame($router, $result->router);
    }

    public function test_returns_null_on_path_mismatch(): void
    {
        $matcher = new PatternMatcher('admin/');
        $router = $this->createMock(Router::class);
        $context = $this->createContext('/public/dashboard', $router);

        $this->assertNull($matcher->match($context, $router));
    }

    public function test_escapes_regex_special_characters_in_pattern(): void
    {
        $matcher = new PatternMatcher('v1.0/files.');
        $router = $this->createMock(Router::class);
        $context = $this->createContext('/v1.0/files.json', $router);

        $result = $matcher->match($context, $router);

        $this->assertNotNull($result);
        $this->assertSame('json', $result->remaining_path);
        $this->assertSame($router, $result->router);
    }

    public function test_empty_pattern_matches_everything_without_consuming_path(): void
    {
        $matcher = new PatternMatcher('');
        $router = $this->createMock(Router::class);
        $context = $this->createContext('/users/list', $router);

        $result = $matcher->match($context, $router);

        $this->assertNotNull($result);
        $this->assertSame('users/list', $result->remaining_path);
        $this->assertSame([], $result->parameters);
        $this->assertSame($router, $result->router);
    }

    public function test_updates_router_reference_in_returned_context(): void
    {
        $matcher = new PatternMatcher('child/');
        $initialRouter = $this->createMock(Router::class);
        $newRouter = $this->createMock(Router::class);

        $context = $this->createContext('/child/action', $initialRouter);

        $result = $matcher->match($context, $newRouter);

        $this->assertNotNull($result);
        $this->assertSame($newRouter, $result->router);
        $this->assertSame($initialRouter, $context->router);
    }

}
