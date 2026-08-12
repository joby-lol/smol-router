<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Request\Request;

/**
 * Useful class for making object-oriented guards that control access to a given Router's content.
 * 
 * Guards are passed the complete original Request and may return null if they have no opinion about the permissions for a given route, or return boolean if they want to affirmatively say to either allow or deny access. The first highest priority guard to return a non-null value wins, and if no guards return a value access is granted by default.
 */
interface GuardInterface
{

    /**
     * Guards are passed the complete original Request and may return null if they have no opinion about the permissions for a given route, or return boolean if they want to affirmatively say to either allow or deny access. The first highest priority guard to return a non-null value wins, and if no guards return a value access is granted by default.
     * 
     * @return bool|null
     */
    public function __invoke(Request $request): bool|null;

}
