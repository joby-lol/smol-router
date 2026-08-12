<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Closure;
use Generator;
use Joby\Smol\Request\Request;

/**
 * @internal class for tracking state as a Request is passed from a Router to its children.
 * 
 * At each layer of nested Router processing a fresh copy is spawned, and they are used to track the current request, remaining unmatched path string, pattern-matched parameter values, parent Router, and previous RouteContext.
 */
readonly class RouteContext
{

    public static function fromRequest(Request $request, Router $router): RouteContext
    {
        return new RouteContext(
            $request,
            $request->url->path,
            [],
            $router,
            null,
        );
    }

    /**
     * @param Request $request the original request
     * @param string $remaining_path any path that has not yet been matched
     * @param array<string,string> $parameters the raw string values of any pattern parameters that have been matched so far
     */
    public function __construct(
        public Request $request,
        public string $remaining_path,
        public array $parameters,
        public Router $router,
        public RouteContext|null $previous,
    ) {}

    /**
     * Create a copy with updated remaining path and added parameters values.
     * 
     * @param string $remaining_path any path that has not yet been matched
     * @param array<string,string> $parameters the raw string values of any new pattern parameters that have been matched so far
     */
    public function with(string $remaining_path, array $parameters, Router $router): RouteContext
    {
        return new RouteContext(
            $this->request,
            $remaining_path,
            array_merge($this->parameters, $parameters),
            $router,
            $this,
        );
    }

    /**
     * Recursively get all parameter factory callbacks defined in this context's router as well as all previous routers.
     * 
     * @return Generator<class-string,Closure(string):(object|null)>
     * @internal
     */
    public function parameterFactoryCallbacks(): Generator
    {
        yield from $this->router->parameterFactoryCallbacks();
        if ($this->previous)
            yield from $this->previous->parameterFactoryCallbacks();
    }

}
