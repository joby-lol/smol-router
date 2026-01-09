<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router\Matchers;

use Joby\Smol\Request\Request;
use Joby\Smol\Router\MatchedRoute;
use Joby\Smol\Router\MatcherInterface;

/**
 * Matches paths that exactly equal a specific string.
 *
 * This is the simplest and most performant matcher for fixed routes.
 *
 * Examples:
 * - Path: "/about" matches only "/about"
 * - Path: "/contact" matches only "/contact"
 * - Path: "/" matches only the root path
 */
class ExactMatcher implements MatcherInterface
{

    /**
     * @param string $path The exact path to match
     */
    public function __construct(
        public readonly string $path
    ) {}

    public function match(string $path, Request $request): MatchedRoute|null
    {
        if ($path === $this->path) {
            return new MatchedRoute($path, $request, []);
        }

        return null;
    }

}
