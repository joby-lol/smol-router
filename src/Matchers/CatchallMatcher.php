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

class CatchallMatcher implements MatcherInterface
{

    public function match(string $path, Request $request): MatchedRoute|null
    {
        return new MatchedRoute($path, $request, []);
    }

}
