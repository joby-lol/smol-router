<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

/**
 * Interface for objects that can be instantiated from strings without needing to be registered with each Router. Any class that implements this interface can be autowired and instantiated for automatic argument injection in handlers and guards.
 */
interface FromStringInterface
{

    /**
     * Return a fully-valid instance of this object if one can be constructed from the given string value. Return null if the string is not valid.
     * 
     * This method must not throw any exceptions.
     */
    public static function fromString(string $value): static|null;

}
