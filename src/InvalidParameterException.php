<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

/**
 * Class indicating that an invalid parameter was provided, which will lead to the Router returning a 400 Bad Request response.
 */
class InvalidParameterException extends \InvalidArgumentException
{

}
