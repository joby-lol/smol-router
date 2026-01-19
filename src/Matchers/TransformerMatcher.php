<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router\Matchers;

use Closure;
use Joby\Smol\Request\Request;
use Joby\Smol\Router\MatchedRoute;

/**
 * Transforms a path before matching it against a child matcher.
 *
 * This matcher acts as a preprocessing step, applying a transformation function to the path
 * before passing it to another matcher. The transformer can modify the path in any way
 * (e.g., lowercase it, normalize it, remove prefixes) or reject the match entirely by returning null.
 *
 * By default captures the original untransformed path as "original_path".
 *
 * Examples:
 * - Case-insensitive matching: new TransformerMatcher(fn($p) => strtolower($p))
 * - Remove query strings: new TransformerMatcher(fn($p) => explode('?', $p)[0])
 * - Composed: $lower = new TransformerMatcher(fn($p) => strtolower($p));
 *   $router->add($lower->with(new ExactMatcher('about')), ...);
 */
class TransformerMatcher extends AbstractComposableMatcher
{

    /** @var Closure(string):(string|null) the transformation method */
    public readonly Closure $transformer;

    /**
     * @param (callable(string):(string|null))|(Closure(string):(string|null)) $transformer A callable to transform the path before passing it to a child matcher. Return null to reject the match.
     * @param string|null $capture_original If set, captures the original path before transformation as this parameter name. Defaults to "original_path". Pass null to disable.
     */
    public function __construct(
        callable|Closure $transformer,
        public readonly string|null $capture_original = 'original_path',
    )
    {
        if (!($transformer instanceof Closure)) {
            $transformer = Closure::fromCallable($transformer);
        }
        $this->transformer = $transformer;
    }

    public function match(string $path, Request $request): MatchedRoute|null
    {
        // No child matcher means no match
        if (!$this->child_matcher) {
            return null;
        }
        // Transform the path
        /** @var string|null $transformed_path */
        $transformed_path = ($this->transformer)($path);
        // Transformer can reject match by returning null
        if ($transformed_path === null) {
            return null;
        }
        // Match transformed path against child matcher
        $match = $this->child_matcher->match($transformed_path, $request);
        if ($match === null) {
            return null;
        }
        // If not capturing original, return child match as-is
        if ($this->capture_original === null) {
            return $match;
        }
        // Add original path to parameters
        $parameters = $match->parameters;
        $parameters[$this->capture_original] = $path;
        return new MatchedRoute($transformed_path, $request, $parameters);
    }

}
