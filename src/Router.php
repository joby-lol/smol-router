<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Closure;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Post\PostException;
use Joby\Smol\Request\Request;
use Joby\Smol\Response\Content\StringContent;
use Joby\Smol\Response\Response;
use Joby\Smol\Router\Matchers\CatchallMatcher;
use Joby\Smol\URL\QueryException;
use ReflectionFunction;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

class Router
{

    /** 
     * Array of guard callbacks that run before normal route matching. Each consists of a matcher, handler callback, and allowed methods. The return value of the handler is used to determine whether to continue processing routes (null), stop processing and block access (false), or stop processing and allow access (true). If all guards return null, normal route processing continues as usual and access is allowed by default.
     * 
     * @var array<int, array<array{matcher: MatcherInterface, handler: Closure(mixed...): (bool|null), method: array<Method>|null}>> $guards
     */
    protected array $guards = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /**
     * Array of routes, organized by priority. Each consists of a matcher, handler callback, and allowed methods. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed. If the handler returns a Response, it will be used as the response for the request. If it returns null, matching attempts will continue to the next route.
     * 
     * @var array<int, array<array{matcher: MatcherInterface, handler: Closure(mixed...): (Response|null), method: array<Method>}>> $routes
     */
    protected array $routes = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /**
     * Array of modifier callbacks that run after normal route matching. Each consists of a matcher, handler callback, and allowed methods. Handler will be passed the Response and any route parameters it requests for injection. If the handler returns a Response, it will be used in place of the Response it was passed, if it returns null, the original Response will be used.
     * 
     * If it returns a FinalResponse, that will be used and no further modifiers will be run.
     * 
     * @var array<int, array<array{matcher: MatcherInterface, handler: Closure(mixed...): (Response|null), method: array<Method>|null}>> $modifiers
     */
    protected array $modifiers = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /** @var (Closure(Request):string)|null $route_extractor a callable that extracts the route string from a Request */
    protected Closure|null $route_extractor = null;

    /** @var (Closure(string):string)|null $route_normalizer a callable that normalizes the route string after it is extracted */
    protected Closure|null $route_normalizer = null;

    /** @var array<class-string<Throwable>, (Closure(Throwable): HttpException)> $exception_class_handlers a map of exception class names and Closures that convert them into HttpException instances */
    protected array $exception_class_handlers = [];

    /**
     * Error page builders organized by status code pattern, then priority. Each entry pairs a matcher with a handler that receives parameter injection and returns Response|null.
     * 
     * Patterns can be specific ("404"), wildcards ("40x", "4xx"), or "default". Evaluation order: specificity first (404 → 40x → 4xx → default), then priority within each level.
     * 
     * Handlers receive the HttpException via a parameter named $exception typed as HttpException, plus any matcher parameters. Return Response to handle the error, null to try the next builder.
     * 
     * @var array<string, array<int, array<array{matcher: MatcherInterface, handler: Closure(mixed...): (Response|null)}>>> $error_response_builders
     */
    protected array $error_response_builders = [];

    /**
     * @var array<string, callable(string): mixed> $typeHandlers a map of type names to handler functions that convert strings to that type, handlers should return null if conversion is not possible
     */
    protected array $typeHandlers = [
        'int'    => [self::class, 'typeHandler_int'],
        'float'  => [self::class, 'typeHandler_float'],
        'bool'   => [self::class, 'typeHandler_bool'],
        'string' => [self::class, 'typeHandler_string'],
    ];

    public function __construct()
    {
        $this->exceptionClassHandler(
            HttpException::class,
            fn(HttpException $e) => $e
        );
        $this->exceptionClassHandler(
            InvalidParameterException::class,
            fn(InvalidParameterException $e) => new HttpException(400, 'Invalid URL parameter', $e)
        );
        $this->exceptionClassHandler(
            QueryException::class,
            fn(QueryException $e) => new HttpException(500, 'Invalid URL query parameter', $e)
        );
        $this->exceptionClassHandler(
            PostException::class,
            fn(PostException $e) => new HttpException(400, 'Invalid POST data', $e)
        );
    }

    /**
     * Add a GET route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the MatchedRoute parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     * 
     * @codeCoverageIgnore this just passes through to add(), so it's not worth testing separately
     */
    public function get(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        return $this->add(
            matcher: $matcher,
            handler: $handler,
            method: Method::GET,
            priority: $priority,
        );
    }

    /**
     * Add a POST route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the MatchedRoute parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     * 
     * @codeCoverageIgnore this just passes through to add(), so it's not worth testing separately
     */
    public function post(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        return $this->add(
            matcher: $matcher,
            handler: $handler,
            method: Method::POST,
            priority: $priority,
        );
    }

    /**
     * Add a PUT route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the MatchedRoute parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     * 
     * @codeCoverageIgnore this just passes through to add(), so it's not worth testing separately
     */
    public function put(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        return $this->add(
            matcher: $matcher,
            handler: $handler,
            method: Method::PUT,
            priority: $priority,
        );
    }

    /**
     * Add a DELETE route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the MatchedRoute parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     * 
     * @codeCoverageIgnore this just passes through to add(), so it's not worth testing separately
     */
    public function delete(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        return $this->add(
            matcher: $matcher,
            handler: $handler,
            method: Method::DELETE,
            priority: $priority,
        );
    }

    /**
     * Add a PATCH route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the MatchedRoute parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     * 
     * @codeCoverageIgnore this just passes through to add(), so it's not worth testing separately
     */
    public function patch(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        return $this->add(
            matcher: $matcher,
            handler: $handler,
            method: Method::PATCH,
            priority: $priority,
        );
    }

    /**
     * Add a route to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the matched route parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * Return a Response to send to the client.
     * 
     * @param Method|array<Method> $method optionally limit the route to specific HTTP methods, or null to apply to all
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     */
    public function add(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Method|array $method = [Method::GET, Method::POST],
        Priority $priority = Priority::NORMAL,
    ): static
    {
        if (!($handler instanceof Closure)) {
            $handler = Closure::fromCallable($handler);
        }
        $this->routes[$priority->value][] = [
            'method'  => is_array($method) ? $method : [$method],
            'matcher' => $matcher,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Add a guard to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the matched route parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * Return null to continue processing, false to deny access (403 Forbidden), or true to allow access and skip remaining guards.
     * 
     * @param Method|array<Method> $method optionally limit the guard to specific HTTP methods, or null to apply to all
     * @param (callable(mixed...): (bool|null))|(Closure(mixed...): (bool|null)) $handler
     */
    public function guard(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Method|array|null $method = null,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        if (!($handler instanceof Closure)) {
            $handler = Closure::fromCallable($handler);
        }
        if ($method !== null && !is_array($method))
            $method = [$method];
        $this->guards[$priority->value][] = [
            'method'  => $method,
            'matcher' => $matcher,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Add a response modifier to the router, using a MatcherInterface, a handler, and an optional Priority. The handler may accept named/typed arguments, and they will be injected from the Matched instance created by the Matcher as needed.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "response" with a type of Response (or a subclass) will be injected with the current Response.
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the matched route parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked, and an error page will be returned to the client.
     * 
     * Return a Response to replace the current response, null to keep the original, or FinalResponse to replace and skip remaining modifiers.
     * 
     * @param Method|array<Method> $method optionally limit the modifier to specific HTTP methods, or null to apply to all
     * @param (callable(mixed...): (Response|null))|(Closure(mixed...): (Response|null)) $handler
     */
    public function modify(
        MatcherInterface $matcher,
        callable|Closure $handler,
        Method|array|null $method = null,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        if (!($handler instanceof Closure)) {
            $handler = Closure::fromCallable($handler);
        }
        if ($method !== null && !is_array($method))
            $method = [$method];
        $this->modifiers[$priority->value][] = [
            'method'  => $method,
            'matcher' => $matcher,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Run the router against the given Request, returning a Response. Returns an error Response if no routes match, or if an exception is thrown while running a handler.
     */
    public function run(Request $request): Response
    {
        // try to extract path, and return error immediately if it fails
        try {
            $path = $this->extractRoute($request);
        }
        catch (Throwable $th) {
            return $this->basicErrorResponse(
                new HttpException(500, 'Error extracting route from request', $th),
            );
        }
        // first run guards, and if they generate an error, use that to build a response
        // @var Response|null $response
        $response = null;
        try {
            // first run guards in priority order
            foreach ($this->guards as $guards) {
                foreach ($guards as $guard) {
                    // check methods first
                    if ($guard['method'] !== null && !in_array($request->method, $guard['method']))
                        continue;
                    // try to match
                    $match = $guard['matcher']->match($path, $request);
                    if (!$match)
                        continue;
                    // we have a match, so we need to run the handler and check the result
                    $handler = $guard['handler'];
                    $result = $this->runGuard($handler, $match);
                    // allow access
                    if ($result === true)
                        break 2; // break out of both loops
                    // block access with a 403 header
                    elseif ($result === false)
                        throw new HttpException(403);
                    // if result is null, continue to next guard
                }
            }
        }
        catch (Throwable $th) {
            $response = $this->errorResponse($th, $path, $request);
        }
        // then, if there is not an error response from running guards, run normal route matching
        try {
            if (!$response) {
                // run route handlers in priority order
                foreach ($this->routes as $routes) {
                    foreach ($routes as $route) {
                        // check methods first
                        if (!in_array($request->method, $route['method']))
                            continue;
                        // try to match
                        $match = $route['matcher']->match($path, $request);
                        if (!$match)
                            continue;
                        // we have a match, so we need to run the handler and return the response if it gives one
                        $handler = $route['handler'];
                        $response = $this->runHandler($handler, $match);
                        if ($response !== null)
                            break 2; // break out of both loops
                    }
                }
                // if there's no response, make a 404 response
                if (!isset($response))
                    $response = $this->errorResponse(
                        new HttpException(404, 'No route matched the request'),
                        $path,
                        $request,
                    );
                // short-circuit here if we have a FinalResponse
                if ($response instanceof FinalResponse)
                    return $response;
            }
        }
        catch (Throwable $th) {
            $response = $this->errorResponse($th, $path, $request);
        }
        try {
            // finally run modifiers in priority order
            foreach ($this->modifiers as $modifiers) {
                foreach ($modifiers as $modifier) {
                    // check methods first
                    if ($modifier['method'] !== null && !in_array($request->method, $modifier['method']))
                        continue;
                    // try to match
                    $match = $modifier['matcher']->match($path, $request);
                    if (!$match)
                        continue;
                    // we have a match, so we need to run the handler and return the response if it gives one
                    $handler = $modifier['handler'];
                    $handler_output = $this->runModifier($handler, $match, $response);
                    $response = $handler_output ?? $response;
                    if ($response instanceof FinalResponse)
                        return $response;
                }
            }
        }
        catch (Throwable $th) {
            return $this->errorResponse($th, $path, $request);
        }
        return $response;
    }

    /**
     * Set a type handler for the given type. Type handlers are used to convert string parameters from the request into typed parameters for handler functions. Set the handler to null to remove the type handler.
     * 
     * Note that this is type-hinted for class-string values in $type, but you can set handlers for scalar types as well (int, float, bool, string, etc.). Static analysis will complain about it, but it will work. Ideally though, you wouldn't be overriding the built-in scalar type handlers anyway -- the option to do so is just provided for completeness, and because disabling it would be more complex than just letting it happen.
     * 
     * @template T of mixed
     * @param class-string<T> $type
     * @param (callable(string): T|null)|null $handler
     */
    public function typeHandler(string $type, callable|null $handler): static
    {
        if ($handler === null) {
            unset($this->typeHandlers[$type]);
            return $this;
        }
        $this->typeHandlers[$type] = $handler;
        return $this;
    }

    /**
     * Set the route extractor callable, which extracts the route portion of the URL from a Request for matching purposes. If set to null, the full path of the URL will be used. This is useful for applications that are not hosted at the root of a domain, or require some other weird extraction logic.
     * 
     * @param (callable(Request): string)|(Closure(Request): string)|null $extractor
     */
    public function routeExtractor(callable|Closure|null $extractor): static
    {
        if ($extractor === null) {
            $this->route_extractor = null;
            return $this;
        }
        if (!($extractor instanceof Closure)) {
            $extractor = Closure::fromCallable($extractor);
        }
        $this->route_extractor = $extractor;
        return $this;
    }

    /**
     * Set the route normalizer callable, which runs after the route is extracted from the request, before matching. The default normalizer strips leading and trailing slashes, so that for example /about/foo/ will be handled the same as about/foo. The root path is represented as an empty string.
     *
     * This default normalization is always applied, even after any additional normalization.
     *
     * @param (callable(string): string)|(Closure(string): string)|null $normalizer
     */
    public function routeNormalizer(callable|Closure|null $normalizer): static
    {
        if ($normalizer === null) {
            $this->route_normalizer = null;
            return $this;
        }
        if (!($normalizer instanceof Closure)) {
            $normalizer = Closure::fromCallable($normalizer);
        }
        $this->route_normalizer = $normalizer;
        return $this;
    }

    /**
     * Register an exception handler for the given exception class. The handler receives the exception and returns an HttpException to convert it to an HTTP error. Set to null to remove a handler.
     * 
     * Exact class matches take precedence over subclass matches. When multiple handlers match, earlier registrations take precedence.
     * 
     * @template T of Throwable
     * @param class-string<T> $exception_class
     * @param (callable(T): HttpException)|(Closure(T): HttpException)|null $handler
     */
    public function exceptionClassHandler(string $exception_class, callable|Closure|null $handler): static
    {
        if ($handler === null) {
            unset($this->exception_class_handlers[$exception_class]);
            return $this;
        }
        if (!($handler instanceof Closure)) {
            $handler = Closure::fromCallable($handler);
        }
        $this->exception_class_handlers[$exception_class] = $handler;
        return $this;
    }

    /**
     * Register an error page builder for status codes. Names can be specific ("404"), patterns ("40x", "4xx"), or "default". Builders are evaluated by specificity first, then priority within each level.
     * 
     * Handler callbacks will have their parameters injected automatically based on their names and types. The following parameter injections are supported:
     * - A parameter named "exception" with type HttpException will be injected with the exception that triggered the error.
     * - A parameter named "path" with type "string" will be injected with the matched path.
     * - A parameter named "request" with a type of Request (or a subclass) will be injected with the matched Request.
     * - Any other parameters will be injected from the matched route parameters, converted to the appropriate type if possible using registered type handlers.
     * 
     * General-purpose parameters are matched by name, and typed using the type handlers registered with the router. If a parameter cannot be provided, and does not have a default value or allow null, an InvalidParameterException will be thrown when the handler is invoked.
     * 
     * Return a Response to handle the error, or null to try the next builder.
     * 
     * @param string $name Status code, pattern, or "default"
     * @param callable|Closure $handler Handler receiving exception, returns Response|null
     * @param MatcherInterface|null $matcher Optional matcher to scope when this builder applies
     * @param Priority $priority Priority within specificity level
     */
    public function addErrorResponseBuilder(
        string $name,
        callable|Closure $handler,
        MatcherInterface|null $matcher = null,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        $matcher = $matcher ?? new CatchallMatcher();
        if (!($handler instanceof Closure))
            $handler = Closure::fromCallable($handler);
        if (!array_key_exists($name, $this->error_response_builders)) {
            $this->error_response_builders[$name] = [
                Priority::HIGH->value   => [],
                Priority::NORMAL->value => [],
                Priority::LOW->value    => [],
            ];
        }
        $this->error_response_builders[$name][$priority->value][] = [
            'matcher' => $matcher,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Extract the route portion of the URL, as it should be used for matching route handlers. If no route extractor is set, this defaults to using the full path of the URL.
     */
    public function extractRoute(Request $request): string
    {
        if ($this->route_extractor === null) {
            return $this->normalizeRoute(
                $request->url->path->__toString(),
            );
        }
        return $this->normalizeRoute(
            ($this->route_extractor)($request),
        );
    }

    /**
     * Apply route normalization, including any custom normalizer that has been added. This step always strips leading and trailing slashes, and represents the root path as an empty string.
     */
    public function normalizeRoute(string $route): string
    {
        // apply custom normalization if applicable
        if ($this->route_normalizer) {
            $route = ($this->route_normalizer)($route);
        }
        // apply standard built-in normalization - strip leading and trailing slashes
        $route = trim($route, '/');
        // return normalized route
        return $route;
    }

    /**
     * Generates an error Response for a given Throwable, so that errors can be returned to the client in a consistent manner. First checks for registered error handlers for specific exception types, and wraps them in appropriate HttpException objects if needed. Then attempts to render an appropriate error response based on the status code, again using registered handlers if available.
     */
    protected function errorResponse(Throwable $error, string $path, Request $request): Response
    {
        // first look for exact class matches
        $handler = null;
        foreach (array_keys($this->exception_class_handlers) as $exception_class) {
            if ($error::class === $exception_class) {
                $handler = $this->exception_class_handlers[$exception_class];
                break;
            }
        }
        // if we haven't found a handler, look for is_a matches to catch subclasses
        if ($handler === null) {
            foreach (array_keys($this->exception_class_handlers) as $exception_class) {
                if (is_a($error, $exception_class)) {
                    $handler = $this->exception_class_handlers[$exception_class];
                    break;
                }
            }
        }
        // if we still haven't found a handler, just use a default one
        if ($handler === null) {
            $handler = fn(Throwable $th): HttpException => new HttpException(500, previous: $th);
        }
        // run the handler to get an HttpException
        $http_exception = $handler($error);
        // @phpstan-ignore-next-line it's worth checking at runtime
        if (!($http_exception instanceof HttpException)) {
            throw new RuntimeException('Exception handler did not return an HttpException instance.');
        }
        // now look for a response handler for the status code
        // handler names are used in increasing specificity, so first we try 404, then 40x, then 4xx, then default
        $possible_handlers = [
            (string) $http_exception->status->code,
            floor($http_exception->status->code / 10) . 'x',
            floor($http_exception->status->code / 100) . 'xx',
            'default',
        ];
        foreach ($possible_handlers as $handler_name) {
            if (array_key_exists($handler_name, $this->error_response_builders)) {
                foreach ($this->error_response_builders[$handler_name] as $builders_by_priority) {
                    foreach ($builders_by_priority as $builder) {
                        // check matcher first
                        $match = $builder['matcher']->match($path, $request);
                        if (!$match)
                            continue;
                        // run the handler
                        $handler = $builder['handler'];
                        $response = $this->runErrorPageBuilderHandler($handler, $match, $http_exception);
                        if ($response)
                            return $response;
                    }
                }
            }
        }
        // as a last resort return a generic response
        return $this->basicErrorResponse($http_exception);
    }

    /**
     * Generate a basic error response for the given HttpException. Basic responses are simple text/plain responses with the status code and reason phrase. These can be used any time an error response is needed, and no custom error page builder is available.
     */
    protected function basicErrorResponse(HttpException $http_exception): Response
    {
        $status = $http_exception->status;
        $response = new Response($status);
        $response->cacheNever();
        $content = new StringContent('Error ' . $status->code . ': ' . $status->reason_phrase);
        $content->setFilename('error-' . $status->code . '.txt');
        $response->setContent($content);
        return $response;
    }

    /** 
     * Runs the given handler with the provided match and returns a Response. Reflects closure and injects arguments from Matched as needed.
     * 
     * @param Closure(mixed...): (Response|null) $handler
     */
    protected function runHandler(Closure $handler, MatchedRoute $match): Response|null
    {
        return $handler(...$this->buildHandlerArguments($handler, $match));
    }

    /** 
     * Runs the given modifier with the provided match and returns a Response. Reflects closure and injects arguments from Matched as needed.
     * 
     * @param Closure(mixed...): (Response|null) $handler
     */
    protected function runModifier(Closure $handler, MatchedRoute $match, Response|null $response = null): Response|null
    {
        return $handler(...$this->buildHandlerArguments($handler, $match, $response));
    }

    /** 
     * Runs the given guard with the provided match and returns a Response. Reflects closure and injects arguments from Matched as needed.
     * 
     * @param Closure(mixed...): (bool|null) $handler
     */
    protected function runGuard(Closure $handler, MatchedRoute $match): bool|null
    {
        return $handler(...$this->buildHandlerArguments($handler, $match));
    }

    /** 
     * Runs the given error page builder with the provided match and returns a Response. Reflects closure and injects arguments from Matched as needed.
     * 
     * @param Closure(mixed...): (Response|null) $handler
     */
    protected function runErrorPageBuilderHandler(
        Closure $handler,
        MatchedRoute $match,
        HttpException $exception,
    ): Response|null
    {
        return $handler(...$this->buildHandlerArguments($handler, $match, null, $exception));
    }

    /**
     * Build an array of arguments to pass to the given handler function, based on its parameter names and types, using the provided MatchedRoute to supply values. Has the ability to optionally include the current Response for modifier handlers, or the exception for error page builders.
     * 
     * @return array<string,mixed>
     */
    protected function buildHandlerArguments(
        Closure $fn,
        MatchedRoute $match,
        Response|null $response = null,
        HttpException|null $exception = null,
    ): array
    {
        $reflection = new ReflectionFunction($fn);
        $parameters = $reflection->getParameters();
        $args = [];
        foreach ($parameters as $param) {
            // get param name as a string
            /** @var non-empty-string $name */
            $name = (string) $param->getName();
            // get a full list of types as an array
            /** @var array<int, string> $types */
            if ($param->getType() === null) {
                $types = [];
            }
            else {
                $types = $param->getType() instanceof ReflectionUnionType
                    ? $param->getType()->getTypes()
                    : [$param->getType()];
                $types = array_map(fn($type) => (string) $type, $types);
            }
            // special handling if the parameter is "path"
            if ($name === 'path') {
                // if no types, or string type, inject the matched path string
                if (!$types || in_array('string', $types)) {
                    $args[$name] = $match->path;
                    continue;
                }
                // if there's no value for 'path' other than the matched path, handle it like we would below, but with the path string
                elseif (!$match->hasParameter('path')) {
                    $args[$name] = $this->valueAsType($match->path, $types);
                    continue;
                }
                // otherwise fall through to normal handling below
            }
            // inject Request object if parameter type matches
            if ($name === 'request') {
                foreach ($types as $typeName) {
                    if (is_a($typeName, Request::class, true)) {
                        $args[$name] = $match->request;
                        continue 2; // continue outer loop
                    }
                }
            }
            // inject Response object if parameter type matches
            if ($name === 'response' && $response !== null) {
                foreach ($types as $typeName) {
                    if (is_a($typeName, Response::class, true)) {
                        $args[$name] = $response;
                        continue 2; // continue outer loop
                    }
                }
            }
            // inject Exception object if parameter type matches
            if ($name === 'exception' && $exception !== null) {
                foreach ($types as $typeName) {
                    if (is_a($typeName, HttpException::class, true)) {
                        $args[$name] = $exception;
                        continue 2; // continue outer loop
                    }
                }
            }
            // try to get parameter from Matched
            if ($match->hasParameter($name)) {
                $args[$name] = $this->valueAsType($match->parameter($name), $types);
            }
            // if not found, see if we have a default value
            elseif ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
            }
            // if still not found, see if we can use null
            elseif ($param->allowsNull()) {
                $args[$name] = null;
            }
            // otherwise we have an error
            else {
                throw new InvalidParameterException('Handler parameter "' . $name . '" is required but was not provided by the Matcher.');
            }
        }
        return $args;
    }

    /**
     * Return the given value cast to one of the provided types, if possible. If not possible, throws an InvalidParameterException.
     * @param array<string> $types
     */
    protected function valueAsType(string|null $value, array $types): mixed
    {
        // if value is null, return null
        if ($value === null)
            return null;
        // if no types, return as is -- we can't validate, and will just have to trust downstream code to handle it
        if (!$types)
            return $value;
        // otherwise we need to check each type, and return the first one that has a matching handler
        foreach ($types as $type) {
            if (array_key_exists($type, $this->typeHandlers)) {
                $typed = ($this->typeHandlers[$type])($value);
                if ($typed !== null)
                    return $typed;
            }
        }
        // if we get here, no types matched
        throw new InvalidParameterException('Handler parameter could not be converted to any of the required types: ' . implode(', ', $types) . '.');
    }

    protected static function typeHandler_int(string $value): ?int
    {
        if (is_numeric($value) && (string) (int) $value === $value) {
            return (int) $value;
        }
        return null;
    }

    protected static function typeHandler_float(string $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    protected static function typeHandler_bool(string $value): ?bool
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on'  => true,
            '0', 'false', 'no', 'off' => false,
            default                   => null,
        };
    }

    protected static function typeHandler_string(string $value): string
    {
        return $value;
    }

}
