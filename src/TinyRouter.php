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
use Joby\Smol\Request\Request;
use Joby\Smol\Response\Response;
use ReflectionFunction;
use ReflectionUnionType;

class TinyRouter
{

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

    /** @var (Closure(Request):string)|null $route_extractor a callable that extracts the route string from a Request */
    protected Closure|null $route_extractor = null;

    /** @var (Closure(string):string)|null $route_normalizer a callable that normalizes the route string after it is extracted */
    protected Closure|null $route_normalizer = null;

    /**
     * @var array<string, callable(string): mixed> $typeHandlers a map of type names to handler functions that convert strings to that type, handlers should return null if conversion is not possible
     */
    protected array $typeHandlers = [
        'int'    => [self::class, 'typeHandler_int'],
        'float'  => [self::class, 'typeHandler_float'],
        'bool'   => [self::class, 'typeHandler_bool'],
        'string' => [self::class, 'typeHandler_string'],
    ];

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
     * Run the router against the given Request, returning a Response. Returns null if no handlers return a Response.
     */
    public function run(Request $request): Response|null
    {
        // try to extract path
        $path = $this->extractRoute($request);
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
                    return $response;
            }
        }
        return null;
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
     * Runs the given handler with the provided match and returns a Response. Reflects closure and injects arguments from Matched as needed.
     * 
     * @param Closure(mixed...): (Response|null) $handler
     */
    protected function runHandler(Closure $handler, MatchedRoute $match): Response|null
    {
        return $handler(...$this->buildHandlerArguments($handler, $match));
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
