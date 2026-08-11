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
 * Exception class indicating that all parameters are available in the URL, but some are not in valid formats to be cast as a callable expected. This is generally not a significant error because it indicates that some dynamic content in the URL does not exist, and should yield a 404 error.
 */
class ParametersInvalidFormatException extends Exception
{

}
