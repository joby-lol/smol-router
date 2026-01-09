<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Joby\Smol\Response\Status;
use RuntimeException;
use Throwable;

/**
 * Class indicating that an HTTP error occurred, which will lead to the Router returning an appropriate HTTP error response.
 */
class HttpException extends RuntimeException
{

    public readonly Status $status;

    public function __construct(int $status_code, string|null $reason_phrase = null, Throwable|null $previous = null)
    {
        $this->status = new Status($status_code, $reason_phrase);
        parent::__construct(
            "HTTP Error {$this->status->code}: {$this->status->reason_phrase}",
            previous: $previous,
        );
    }

}
