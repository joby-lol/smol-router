<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Response\Response;

/**
 * A Response which, when returned from a handler, indicates that no further transformers should be run.
 */
class FinalResponse extends Response
{

}
