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
 * Matches URL patterns with named parameters, such as "users/:id" or "posts/:post_id/comments/:comment_id".
 *
 * Parameters are specified with a colon prefix (e.g., ":id", ":name") and will match any non-slash characters.
 * The matched values are extracted and made available as parameters in the MatchedRoute.
 *
 * Examples:
 * - Pattern: "users/:id" matches "/users/123" with parameter "id" = "123"
 * - Pattern: "posts/:post_id/comments/:comment_id" matches "posts/456/comments/789" with parameters "post_id" = "456", "comment_id" = "789"
 */
class PatternMatcher implements MatcherInterface
{

    protected string|null $regex = null;
    /** @var array<string> */
    protected array $parameterNames = [];

    /**
     * @param string $pattern The pattern to match, with parameters specified as :name
     */
    public function __construct(
        public readonly string $pattern
    ) {
    }

    public function match(string $path, Request $request): MatchedRoute|null
    {
        // lazy-compile the pattern only when needed
        if ($this->regex === null)
            list($this->regex, $this->parameterNames) = PatternHelper::compilePattern($this->pattern, true, true);
        // match and return
        if (preg_match($this->regex, $path, $matches)) {
            $parameters = [];
            foreach ($this->parameterNames as $index => $name) {
                // match indices start at 1 because 0 is the full match
                $parameters[$name] = $matches[$index + 1];
            }
            return new MatchedRoute($path, $request, $parameters);
        }
        return null;
    }


}
