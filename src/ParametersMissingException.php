<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Exception;

/**
 * Exception class indicating that a parameter expected by a callback is missing from the parameters in the current context. This is a significant error and should generally yield a 500 server error.
 */
class ParametersMissingException extends Exception
{

}
