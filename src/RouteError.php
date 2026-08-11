<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Throwable;

readonly class RouteError
{

    public function __construct(
        public int $http_code,
        public RouteContext $context,
        public string $message,
        public Throwable|null $exception = null,
    ) {}

    public function isHardFailure(): bool
    {
        return $this->http_code === 403
            || $this->http_code >= 500
            || $this->exception !== null;
    }

}
