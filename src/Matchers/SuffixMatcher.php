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
 * Matches paths that end with a specific suffix.
 *
 * Can be composed with another matcher using the with() method to match patterns within the base path.
 * By default captures the base path before the suffix as "suffix_base".
 *
 * Examples:
 * - Basic: new SuffixMatcher(".json") matches "users.json", "posts/123.json", etc.
 * - Composed: $json = new SuffixMatcher(".json"); $json->with(new PatternMatcher("users/:id"))
 * - Reusable: Define once, compose multiple times:
 *   $json = new SuffixMatcher(".json");
 *   $router->add($json->with(new PatternMatcher("users/:id")), ...);
 *   $router->add($json->with(new PatternMatcher("posts/:id")), ...);
 */
class SuffixMatcher implements MatcherInterface
{

    /**
     * @param string $suffix The suffix to match at the end of the path
     * @param string|null $capture_base If set, captures the base path before the suffix as this parameter name. Defaults to "suffix_base". Pass null to disable.
     * @param MatcherInterface|null $matcher Optional child matcher to match against the base path. Typically set via with() method.
     */
    public function __construct(
        public readonly string $suffix,
        public readonly ?string $capture_base = 'suffix_base',
        public readonly ?MatcherInterface $matcher = null,
    ) {}

    /**
     * Create a new SuffixMatcher composed with a child matcher.
     * The child matcher will be matched against the base path before the suffix.
     *
     * @param MatcherInterface $matcher The matcher to compose with
     * @return self A new SuffixMatcher instance with the child matcher
     */
    public function with(MatcherInterface $matcher): self
    {
        return new self($this->suffix, $this->capture_base, $matcher);
    }

    public function match(string $path, Request $request): MatchedRoute|null
    {
        if (!str_ends_with($path, $this->suffix)) {
            return null;
        }

        // Extract base before suffix
        $base = substr($path, 0, -strlen($this->suffix));
        $parameters = [];

        // If child matcher provided, match it against base
        if ($this->matcher !== null) {
            $childMatch = $this->matcher->match($base, $request);

            // Child matcher must match for composed matcher to match
            if ($childMatch === null) {
                return null;
            }

            // Collect parameters from child matcher
            $parameters = $childMatch->parameters;
        }

        // Capture base if configured
        if ($this->capture_base !== null) {
            $parameters[$this->capture_base] = $base;
        }

        return new MatchedRoute($path, $request, $parameters);
    }

}

