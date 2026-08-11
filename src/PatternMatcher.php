<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

class PatternMatcher
{

    protected string $regex;

    /** @var array<string> */
    protected array $parameter_names;

    public function __construct(
        string $pattern,
    )
    {
        $this->parameter_names = [];
        // Escape regex special characters except for our parameter markers
        $pattern = preg_quote($pattern, '#');
        // Replace escaped colons back (since we want to use them for parameters)
        // and extract parameter names
        $pattern = preg_replace_callback(
            '#\\\\:([a-zA-Z_][a-zA-Z0-9_]*)#',
            function ($matches) {
                $this->parameter_names[] = $matches[1];
                return '([^/]+)'; // Match any characters except slashes
            },
            $pattern,
        );
        // Create the final regex with anchors
        $this->regex = '#^'
            . $pattern
            . '#';
    }

    public function match(RouteContext $context): RouteContext|null
    {
        // attempt regex match
        if (!preg_match($this->regex, $context->remaining_path, $matches)) {
            return null;
        }
        // $matches[0] is the entire matched prefix
        $matched_length = strlen($matches[0]);
        $new_remaining_path = substr($context->remaining_path, $matched_length);
        // combine extracted names with match groups
        $parameters = [];
        foreach ($this->parameter_names as $index => $name) {
            $parameters[$name] = $matches[$index + 1];
        }
        // return a new context with the new remaining path and parameters
        return $context->with(
            $new_remaining_path,
            $parameters,
            $context->router,
        );
    }

}
