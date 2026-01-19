<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router\Matchers;

class PatternHelper
{

    /**
     * Compile a pattern like "users/:id" into a regex string and extract parameter names. Returns an array with the regex strings first, followed by parameter names. So you can call it like `list($regex, $paramNames) = PatternHelper::compilePattern($pattern);`
     * 
     * @param string $pattern The pattern to compile
     * @return array{0: string, 1: array<string>} An array where the first element is the regex string, and the second element is an array of parameter names
     */
    public static function compilePattern(
        string $pattern,
        bool $anchor_start,
        bool $anchor_end,
    ): array
    {
        // Reset parameter names
        $parameterNames = [];

        // Escape regex special characters except for our parameter markers
        $pattern = preg_quote($pattern, '#');

        // Replace escaped colons back (since we want to use them for parameters)
        // and extract parameter names
        $pattern = preg_replace_callback(
            '#\\\\:([a-zA-Z_][a-zA-Z0-9_]*)#',
            function ($matches) use (&$parameterNames) {
                $parameterNames[] = $matches[1];
                return '([^/]+)'; // Match any characters except slashes
            },
            $pattern,
        );

        // Create the final regex with anchors
        $regex = '#'
            . ($anchor_start ? '^' : '')
            . $pattern
            . ($anchor_end ? '$' : '')
            . '#';

        return [$regex, $parameterNames];
    }

}
