<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

/**
 * Tiers for controlling the priority of callbacks in Routers.
 */
enum Priority: int
{

    case LOW = 1;

    case NORMAL = 2;

    case HIGH = 3;

}
