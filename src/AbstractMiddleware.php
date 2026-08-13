<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Closure;
use Joby\Smol\Request\Request;
use Joby\Smol\Response\Response;

/**
 * Object-oriented middleware class designed to make building smolRouter middleware easy and predictable.
 */
abstract class AbstractMiddleware
{

    /**
     * Request pre-processing stage is passed the original request and may return three different types:
     * - Return null to do nothing
     * - Response to short-circuit further layers and Routers and return that Response immediately, such as for output cache hits. Will still be processed by outer layers and this layer's processResponse.
     * - RouteError to indicate a problem
     *   - Soft fails will accumulate and only return if future steps
     *   - Hard fails short-circuit similar to returning a Response
     */
    protected function processRequest(Request $request, RouteContext $context): Response|RouteError|null
    {
        return null;
    }

    /**
     * Called when inner layers or processRequest return a valid Response object. This callback may alter it or return a different Response or a RouteError.
     */
    protected function processResponse(Response $response, RouteContext $context): Response|RouteError
    {
        return $response;
    }

    /**
     * Called when inner layers or processRequest return a RouteError object. This callback may alter it or return a different RouteError, or even convert it to a RouteError.
     */
    protected function processError(RouteError $error, RouteContext $context): Response|RouteError
    {
        return $error;
    }

    /**
     * @param Closure():(Response|RouteError) $next
     */
    public function __invoke(
        Request $request,
        Closure $next,
        RouteContext $context,
    ): Response|RouteError
    {
        // run preprocessing step on Request only
        $result = $this->processRequest($request, $context);
        if ($result instanceof Response)
            return $this->processResponse($result, $context);
        if ($result instanceof RouteError)
            return $this->processError($result, $context);
        // get result from inner step
        $result = ($next)();
        if ($result instanceof Response)
            return $this->processResponse($result, $context);
        else
            return $this->processError($result, $context);
    }

}
