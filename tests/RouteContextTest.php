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

class RouteContextTest extends TestCase
{

    private function createRealRequest(string $path = '/foo/bar'): Request
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

    private function createMockRouter(array $factories = []): Router
    {
        $router = $this->createMock(Router::class);
        $router->method('parameterFactoryCallbacks')
            ->willReturn($factories);

        return $router;
    }

    public function test_from_request_creates_initial_context(): void
    {
        $request = $this->createRealRequest('/users/123');
        $router = $this->createMockRouter();

        $context = RouteContext::fromRequest($request, $router);

        $this->assertSame($request, $context->request);
        $this->assertSame('users/123', $context->remaining_path);
        $this->assertSame([], $context->parameters);
        $this->assertSame($router, $context->router);
        $this->assertNull($context->previous);
    }

    public function test_with_spawns_child_context_with_merged_parameters(): void
    {
        $request = $this->createRealRequest('/users/123/posts/456');
        $parentRouter = $this->createMockRouter();
        $childRouter = $this->createMockRouter();

        $parentContext = RouteContext::fromRequest($request, $parentRouter);

        $childContext = $parentContext->with(
            'posts/456',
            ['user_id' => '123'],
            $childRouter,
        );

        $this->assertSame($request, $childContext->request);
        $this->assertSame('posts/456', $childContext->remaining_path);
        $this->assertSame(['user_id' => '123'], $childContext->parameters);
        $this->assertSame($childRouter, $childContext->router);
        $this->assertSame($parentContext, $childContext->previous);

        // Ensure parent context remains immutable
        $this->assertSame('users/123/posts/456', $parentContext->remaining_path);
        $this->assertSame([], $parentContext->parameters);
    }

    public function test_with_merges_subsequent_parameters_overwriting_duplicates(): void
    {
        $request = $this->createRealRequest('/a/b/c');
        $router1 = $this->createMockRouter();
        $router2 = $this->createMockRouter();
        $router3 = $this->createMockRouter();

        $context1 = RouteContext::fromRequest($request, $router1);
        $context2 = $context1->with('b/c', ['id' => '1', 'type' => 'user'], $router2);
        $context3 = $context2->with('c', ['id' => '2'], $router3);

        $this->assertSame(
            ['id' => '2', 'type' => 'user'],
            $context3->parameters,
        );
    }

    public function test_parameter_factory_callbacks_yields_recursively_child_first(): void
    {
        $factoryA = fn() => null;
        $factoryB = fn() => null;
        $factoryC = fn() => null;

        $rootRouter = $this->createMockRouter(['ClassA' => $factoryA]);
        $parentRouter = $this->createMockRouter(['ClassB' => $factoryB]);
        $childRouter = $this->createMockRouter(['ClassC' => $factoryC]);

        $rootContext = RouteContext::fromRequest($this->createRealRequest('/sub'), $rootRouter);
        $parentContext = $rootContext->with('sub', [], $parentRouter);
        $childContext = $parentContext->with('', [], $childRouter);

        $yielded = [];
        foreach ($childContext->parameterFactoryCallbacks() as $class => $factory) {
            $yielded[$class] = $factory;
        }

        $this->assertSame(
            [
                'ClassC' => $factoryC,
                'ClassB' => $factoryB,
                'ClassA' => $factoryA,
            ],
            $yielded,
        );
    }

}
