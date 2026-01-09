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

    protected string $regex;
    /** @var array<string> */
    protected array $parameterNames = [];

    /**
     * @param string $pattern The pattern to match, with parameters specified as :name
     */
    public function __construct(
        public readonly string $pattern
    ) {
        $this->compilePattern();
    }

    public function match(string $path, Request $request): MatchedRoute|null
    {
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

    /**
     * Compiles the pattern into a regex and extracts parameter names.
     */
    protected function compilePattern(): void
    {
        // Reset parameter names
        $this->parameterNames = [];

        // Escape regex special characters except for our parameter markers
        $pattern = preg_quote($this->pattern, '#');

        // Replace escaped colons back (since we want to use them for parameters)
        // and extract parameter names
        $pattern = preg_replace_callback(
            '#\\\\:([a-zA-Z_][a-zA-Z0-9_]*)#',
            function ($matches) {
                $this->parameterNames[] = $matches[1];
                return '([^/]+)'; // Match any characters except slashes
            },
            $pattern
        );

        // Create the final regex with anchors
        $this->regex = '#^' . $pattern . '$#';
    }

}
