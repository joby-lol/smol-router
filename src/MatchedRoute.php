<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Request\Request;

class MatchedRoute
{

    /**
     * @param Request $request The request that was matched.
     * @param array<string, mixed> $parameters The parameters extracted from the request, if they are strings they may be converted later during injection
     */
    public function __construct(
        public readonly string $path,
        public readonly Request $request,
        public array $parameters = [],
    ) {}

    /**
     * Whether or not this match contains a given parameter.
     */
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * The value of a given parameter if it exists, otherwise null.
     */
    public function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

}
