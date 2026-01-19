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

/**
 * Matches paths that end with a specific suffix.
 *
 * Can be composed with another matcher using the with() method to match patterns within the suffix.
 * By default captures the remainder before the suffix as "suffix_remainder".
 *
 * Examples:
 * - Basic: new SuffixPatternMatcher("api/") matches "api/users", "api/posts/123", etc.
 * - Composed: $api = new SuffixPatternMatcher("api/v1/"); $api->with(new PatternMatcher("users/:id"))
 * - Reusable: Define once, compose multiple times:
 *   $apiV1 = new SuffixPatternMatcher("api/v1/");
 *   $router->add($apiV1->with(new PatternMatcher("users/:id")), ...);
 *   $router->add($apiV1->with(new PatternMatcher("posts/:id")), ...);
 * 
 * Note that a leading slash is NOT automatically added to the suffix. If you omit the leading slash like "users", it will match "apiusers" as well as "api/users".
 */
class SuffixPatternMatcher extends AbstractComposableMatcher
{

    protected string|null $regex = null;

    /** @var array<string> */
    protected array $parameterNames = [];

    /**
     * Matches URL patterns with named parameters, such as "users/:id" or "posts/:post_id/comments/:comment_id". 
     *
     * Parameters are specified with a colon prefix (e.g., ":id", ":name") and will match any non-slash characters.
     * The matched values are extracted and made available as parameters in the MatchedRoute.
     *
     * Examples:
     * - Pattern: "users/:id" matches "/users/123" with parameter "id" = "123"
     * - Pattern: "posts/:post_id/comments/:comment_id" matches "posts/456/comments/789" with parameters "post_id" = "456", "comment_id" = "789"
     * 
     * @param string $suffix_pattern The suffix pattern to match at the end of the path
     * @param string|null $capture_remainder If set, captures the remainder as this parameter name. Defaults to "suffix_remainder". Pass null to disable.
     */
    public function __construct(
        public readonly string $suffix_pattern,
        public readonly ?string $capture_remainder = 'suffix_remainder',
    ) {}

    public function match(string $path, Request $request): MatchedRoute|null
    {
        // lazy-compile the pattern only when needed
        if ($this->regex === null)
            [$this->regex, $this->parameterNames] = PatternHelper::compilePattern($this->suffix_pattern, false, true);

        // attempt to match
        $match = preg_match($this->regex, $path, $matches);
        if ($match === 0)
            return null;

        // start building parameters
        $parameters = [];
        foreach ($this->parameterNames as $index => $name) {
            // match indices start at 1 because 0 is the full match
            $parameters[$name] = $matches[$index + 1];
        }

        // Extract remainder before suffix_pattern
        $remainder = substr($path, 0, strlen($path) - strlen($matches[0]));

        // If child matcher provided, match it against remainder
        if ($this->child_matcher !== null) {
            $childMatch = $this->child_matcher->match($remainder, $request);

            // Child matcher must match for composed matcher to match
            if ($childMatch === null) {
                return null;
            }

            // Collect parameters from child matcher
            $parameters = array_merge($parameters, $childMatch->parameters);
        }

        // Capture remainder if configured
        if ($this->capture_remainder !== null) {
            $parameters[$this->capture_remainder] = $remainder;
        }

        return new MatchedRoute($path, $request, $parameters);
    }

}
