<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router\Matchers;

use Joby\Smol\Router\ComposableMatcherInterface;
use Joby\Smol\Router\MatcherInterface;

abstract class AbstractComposableMatcher implements ComposableMatcherInterface
{

    protected MatcherInterface|null $child_matcher = null;

    /**
     * @inheritDoc
     */
    public function with(MatcherInterface $matcher): static
    {
        // If there's already a child matcher and it's composable, delegate to it    
        if ($this->child_matcher instanceof ComposableMatcherInterface) {
            $new = clone $this;
            $new->child_matcher = $this->child_matcher->with($matcher);
            return $new;
        }
        // Otherwise, set the child matcher directly
        $new = clone $this;
        $new->child_matcher = $matcher;
        return $new;
    }

}
