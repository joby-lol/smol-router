<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Throwable;

/**
 * Class representing an error that occurred during routing or route generation.
 * 
 * Errors include a suggested HTTP status code, the RouteContext in which they were generated, a message, and if applicable an Exception that preceded them
 * 
 * They may be classified as "hard failures" if they include an Exception, or if their HTTP status is 403 or >= 500. Hard failures abort further processing in the current Router and return immediately. Soft failures may not be returned by the Router if either a Response is generated or a hard failure arises. In the case of multiple soft failures only the first one triggered is returned.
 */
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
