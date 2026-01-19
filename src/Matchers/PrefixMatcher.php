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
 * Matches paths that start with a specific prefix.
 *
 * Can be composed with another matcher using the with() method to match patterns within the prefix.
 * By default captures the remainder after the prefix as "prefix_remainder".
 *
 * Examples:
 * - Basic: new PrefixMatcher("api/") matches "api/users", "api/posts/123", etc.
 * - Composed: $api = new PrefixMatcher("api/v1/"); $api->with(new PatternMatcher("users/:id"))
 * - Reusable: Define once, compose multiple times:
 *   $apiV1 = new PrefixMatcher("api/v1/");
 *   $router->add($apiV1->with(new PatternMatcher("users/:id")), ...);
 *   $router->add($apiV1->with(new PatternMatcher("posts/:id")), ...);
 */
class PrefixMatcher extends AbstractComposableMatcher
{

    /**
     * @param string $prefix The prefix to match at the beginning of the path
     * @param string|null $capture_remainder If set, captures the remainder as this parameter name. Defaults to "prefix_remainder". Pass null to disable.
     */
    public function __construct(
        public readonly string $prefix,
        public readonly ?string $capture_remainder = 'prefix_remainder',
    ) {}

    public function match(string $path, Request $request): MatchedRoute|null
    {
        if (!str_starts_with($path, $this->prefix)) {
            return null;
        }

        // Extract remainder after prefix
        $remainder = substr($path, strlen($this->prefix));
        $parameters = [];

        // If child matcher provided, match it against remainder
        if ($this->child_matcher !== null) {
            $childMatch = $this->child_matcher->match($remainder, $request);

            // Child matcher must match for composed matcher to match
            if ($childMatch === null) {
                return null;
            }

            // Collect parameters from child matcher
            $parameters = $childMatch->parameters;
        }

        // Capture remainder if configured
        if ($this->capture_remainder !== null) {
            $parameters[$this->capture_remainder] = $remainder;
        }

        return new MatchedRoute($path, $request, $parameters);
    }

}

