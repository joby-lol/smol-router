<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Request\Request;

interface ComposableMatcherInterface extends MatcherInterface
{

    public function with(MatcherInterface $matcher): self;

}
