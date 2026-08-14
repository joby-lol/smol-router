<?php

/**
 * smolRouter
 * https://github.com/joby-lol/smol-router
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Router;

use Closure;
use Joby\Smol\Cast\Cast;
use Joby\Smol\Request\Method;
use Joby\Smol\Request\Request;
use Joby\Smol\Response\Response;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

class Router
{

    /** @var array<int, ReflectionFunction> */
    protected static array $reflection_cache = [];

    protected PatternMatcher $pattern;

    /**
     * Callback to be used for generating a response if this Router matches the request exactly.
     * @var (Closure(never):(Response|RouteError|null))|null
     */
    protected Closure|null $handler;

    /**
     * Array of Methods that are allowed in this Router and its children.
     * @var Method[]
     */
    protected array $method;

    /**
     * Array of middleware callbacks that will wrap execution from before guards to after rendering.
     * @var array<int,array<Closure(never):(Response|RouteError)>>
     */
    protected array $middleware = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /**
     * Array of guards that will be required to match for routing to continue. The first one to return a bool will take priority (either in favor or against access).
     * @var array<int, array<int, Closure(never): (bool|null)>>
     */
    protected array $guards = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /**
     * Array of sub-routers that can be matched by this router. The first one to match will take priority. These will apply any time this router matches the beginning of a request's path, but there is still some path text remaining after it.
     * @var array<int,array<int,Router>>
     */
    protected array $routers = [
        Priority::HIGH->value   => [],
        Priority::NORMAL->value => [],
        Priority::LOW->value    => [],
    ];

    /**
     * Array of parameter factories that can be used to instantiate objects from strings for parameter injection.
     * @var array<class-string,Closure(string):(object|null)>
     */
    protected array $parameter_factories = [];

    /**
     * @param string $pattern
     * @param (callable(never):(Response|RouteError|null))|(Closure(never):(Response|RouteError|null))|null $handler
     * @param Method|Method[] $method
     */
    public function __construct(
        string $pattern = '',
        callable|Closure|null $handler = null,
        Method|array $method = [Method::GET, Method::POST],
    )
    {
        $this->setPattern($pattern);
        $this->setHandler($handler);
        $this->setMethod($method);
    }

    /**
     * Run this Router on a given Request or existing RouteContext. Will attempt to match the Request to this as well as all children. Returns a completed Response, or a RouteError if no valid responses could be returned. 
     * 
     * @param Request|RouteContext $request_or_context
     * @return Response|RouteError
     */
    public function run(Request|RouteContext $request_or_context): Response|RouteError
    {
        // ensure we have a context object
        // construct a fresh one from the Request if we are passed a raw Request
        $incoming_context = $request_or_context instanceof RouteContext
            ? $request_or_context
            : RouteContext::fromRequest($request_or_context, $this);
        // check that method is allowed
        // if it isn't that's a hard failure
        if (!in_array($incoming_context->request->method, $this->method))
            return new RouteError(405, $incoming_context, 'Method not allowed');
        // check if we match this context
        // if we don't that's a hard short circuit, this request isn't for us or any children
        $context = $this->pattern->match($incoming_context, $this);
        if (!$context)
            return new RouteError(404, $incoming_context, 'Not found');
        // build stack inside out around core
        $core = fn(RouteContext $ctx) => $this->dispatchDownstream($ctx);
        $pipeline = array_reduce(
            array_reverse(array_merge(
                $this->middleware[Priority::HIGH->value],
                $this->middleware[Priority::NORMAL->value],
                $this->middleware[Priority::LOW->value],
            )),
            /**
             * @param Closure():Response|RouteError $next
             * @param Closure(never):(Response|RouteError) $middleware
             */
            function (Closure $next, Closure $middleware) use ($context) {
                return function () use ($middleware, $next, $context): Response|RouteError {
                    return $this->injectParametersAndExecute($middleware, $context, $next);
                };
            },
            fn() => $core($context)
        );
        // execute stack and capture potential injection/unhandled errors within middleware
        try {
            return $pipeline();
        }
        catch (ParametersMissingException $th) {
            return new RouteError(500, $context, 'Middleware parameters not available', $th);
        }
        catch (Throwable $th) {
            return new RouteError(500, $context, 'Unhandled exception in middleware', $th);
        }
    }

    /**
     * Add one or more middleware callbacks to this Router.
     * 
     * @param (callable(never):(Response|RouteError))|(Closure(never):(Response|RouteError))|array<(Closure(never):(Response|RouteError))|(callable(never):(Response|RouteError))> $middleware
     */
    public function addMiddleware(
        callable|Closure|array $middleware,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        array_push($this->middleware[$priority->value], ...$this->normalizeMiddleware($middleware));
        return $this;
    }

    /**
     * Internal core callback for being dispatched in the middle of the middleware onion stack.
     * 
     * @return Response|RouteError
     */
    protected function dispatchDownstream(RouteContext $context): Response|RouteError
    {
        // this is where we start trying to accumulate the first soft error we see
        $first_soft_error = null;
        // check guards
        // guard fails are also hard failures
        foreach ($this->guards as $guards)
            foreach ($guards as $guard) {
                // try to execute guard
                try {
                    $guard_result = $this->injectParametersAndExecute($guard, $context);
                    if ($guard_result === false)
                        return new RouteError(403, $context, 'Access denied');
                    elseif ($guard_result === true)
                        break 2;
                }
                // invalid format parameters is a soft error, keep it and proceed to child routers because one of them might match
                catch (ParametersInvalidFormatException $th) {
                    $first_soft_error = new RouteError(404, $context, 'Not found');
                }
                // missing parameters means guard expectations do not match pattern matching construction, this is a serious misconfiguration and hard error
                catch (ParametersMissingException $th) {
                    return new RouteError(500, $context, 'Guard parameters not available', $th);
                }
                // any other exception is also a serious hard error
                catch (Throwable $th) {
                    return new RouteError(500, $context, 'Unhandled exception', $th);
                }
            }
        // if there is no remaining path check if we have a handler and use that
        // handler may return null, in which case child routers will be queried
        if (!$context->remaining_path && $this->handler) {
            try {
                $result = $this->injectParametersAndExecute($this->handler, $context);
                if ($result !== null) {
                    // if result is a response return it immediately
                    if ($result instanceof Response)
                        return $result;
                    // if result is a hard failure, return it immediately
                    if ($result->isHardFailure())
                        return $result;
                    // otherwise store the first soft failure we get
                    $first_soft_error ??= $result;
                }
            }
            // invalid format parameters is a soft error, keep it and proceed to child routers because one of them might match
            catch (ParametersInvalidFormatException $th) {
                $first_soft_error ??= new RouteError(404, $context, 'Not found');
            }
            // missing parameters means handler expectations do not match pattern matching construction, this is a serious misconfiguration and hard error
            catch (ParametersMissingException $th) {
                return new RouteError(500, $context, 'Handler parameters not available', $th);
            }
            // any other exception is also a serious hard error
            catch (Throwable $th) {
                return new RouteError(500, $context, 'Unhandled exception', $th);
            }
        }
        // try to match child routers
        // continue accumulating the first soft error we see
        foreach ($this->routers as $rs)
            foreach ($rs as $router) {
                $result = $router->run($context);
                // if result is a response return it immediately
                if ($result instanceof Response)
                    return $result;
                // if result is a hard failure, return it immediately
                if ($result->isHardFailure())
                    return $result;
                // otherwise store the first soft failure we get
                $first_soft_error ??= $result;
            }
        // if nothing matched return the last error reported, or a 404
        return $first_soft_error ?? new RouteError(404, $context, 'Not found');
    }

    /**
     * Set the HTTP method/methods that are allowed to match this router.
     * 
     * @param Method|Method[] $method
     */
    public function setMethod(Method|array $method): static
    {
        $this->method = is_array($method) ? $method : [$method];
        return $this;
    }

    /**
     * Add one or more Routers to be matched against the remaining path after this router runs, effectively making them route on subdirectories/suffixes of this Router's pattern.
     * 
     * @param Router|Router[] $routers
     */
    public function addRouter(Router|array $routers, Priority $priority = Priority::NORMAL): static
    {
        $this->routers[$priority->value] = array_merge(
            $this->routers[$priority->value],
            is_array($routers) ? array_values($routers) : [$routers]
        );
        return $this;
    }

    /**
     * Add a permissions guard to this Router. It will be run before any handler callbacks to determine whether access should be granted.
     * 
     * Guards are passed the complete original Request and may return null if they have no opinion about the permissions for a given route, or return boolean if they want to affirmatively say to either allow or deny access. The first highest priority guard to return a non-null value wins, and if no guards return a value access is granted by default.
     * 
     * @param (callable(Request):(bool|null))|(Closure(never):(bool|null))|array<int, (Closure(never):(bool|null))|(callable(Request):(bool|null))> $guards
     */
    public function addGuard(
        callable|Closure|array $guards,
        Priority $priority = Priority::NORMAL,
    ): static
    {
        array_push($this->guards[$priority->value], ...$this->normalizeGuards($guards));
        return $this;
    }

    /**
     * Set the pattern that this Router will match. Do not include leading slashes, but do include trailing slashes if desired as they are not matched by default.
     */
    public function setPattern(string $pattern): static
    {
        $this->pattern = new PatternMatcher($pattern);
        return $this;
    }

    /**
     * @param (callable(never):(Response|RouteError|null))|(Closure(never):(Response|RouteError|null))|null $handler
     */
    public function setHandler(callable|Closure|null $handler): static
    {
        if ($handler === null) {
            $this->handler = null;
        }
        else {
            if (!($handler instanceof Closure))
                $handler = Closure::fromCallable($handler);
            $this->handler = $handler;
        }
        return $this;
    }

    /**
     * Add a callable factory that will be passed a string and asked to instantiate an object of the given class, when that class is used as a type hint in guards or handlers and it is being constructed for argument injection.
     * 
     * @param class-string $class
     * @param (callable(string):(object|null)) $factory
     */
    public function addParameterFactory(string $class, callable $factory): static
    {
        if (!($factory instanceof Closure))
            $factory = Closure::fromCallable($factory);
        $this->parameter_factories[$class] = $factory;
        return $this;
    }

    /**
     * @template ReturnType
     * @param callable(never):ReturnType $callback
     * @param (Closure():(Response|RouteError))|null $next
     * @return ReturnType
     */
    protected function injectParametersAndExecute(callable $callback, RouteContext $context, Closure|null $next = null): mixed
    {
        if (!($callback instanceof Closure))
            $callback = Closure::fromCallable($callback);
        $reflection = $this->getReflection($callback);
        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            // reserved name $context will always be the RouteContext
            if ($parameter->name === 'context') {
                $args[$parameter->name] = $context;
                continue;
            }
            // reserved name $next will always be the next middleware
            if ($parameter->name === 'next') {
                $args[$parameter->name] = $next;
                continue;
            }
            // reserved name $request will always be $context->request
            if ($parameter->name === 'request') {
                $args[$parameter->name] = $context->request;
                continue;
            }
            // reserved name $remaining_path will always be $context->remaining_path
            if ($parameter->name === 'remaining_path') {
                $args[$parameter->name] = $context->remaining_path;
                continue;
            }
            // reserved name $all_parameters will always be $context->parameters
            if ($parameter->name === 'all_parameters') {
                $args[$parameter->name] = $context->parameters;
                continue;
            }
            // try to build anything else from a parameter
            $string_value = $context->parameters[$parameter->name] ?? null;
            // handle case where value doesn't exist in parameters
            if ($string_value === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $args[$parameter->name] = $parameter->getDefaultValue();
                    continue;
                }
                elseif ($parameter->allowsNull()) {
                    $args[$parameter->name] = null;
                    continue;
                }
                else {
                    throw new ParametersMissingException("Argument {$parameter->name} cannot be null");
                }
            }
            // value does exist, if there is no type hint pass it as a string
            $type = $parameter->getType();
            if ($type === null) {
                $args[$parameter->name] = $string_value;
                continue;
            }
            // value exists and has a type hint
            $type_strings = $this->getTypeStrings($type);
            $type_strings = $this->sortTypeStrings($type_strings);
            foreach ($type_strings as $type_string) {
                // string is easy, assign and break immediately
                if ($type_string === 'string') {
                    $args[$parameter->name] = $string_value;
                    break;
                }
                // try to cast as int
                if ($type_string === 'int') {
                    $value = Cast::tryInt($string_value);
                    if ($value !== null) {
                        $args[$parameter->name] = $value;
                        break;
                    }
                    continue;
                }
                // try to cast as a float
                if ($type_string === 'float') {
                    $value = Cast::tryFloat($string_value);
                    if ($value !== null) {
                        $args[$parameter->name] = $value;
                        break;
                    }
                    continue;
                }
                // try to cast as bool
                if ($type_string === 'bool') {
                    $value = Cast::tryBool($string_value);
                    if ($value !== null) {
                        $args[$parameter->name] = $value;
                        break;
                    }
                    continue;
                }
                // try to cast as object
                if (class_exists($type_string, true)) {
                    $value = $this->buildArgumentObject($type_string, $string_value, $context);
                    if ($value !== null) {
                        $args[$parameter->name] = $value;
                        break;
                    }
                    continue;
                }
            }
            // throw an exception if it's null and not allowed to be null
            if ($args[$parameter->name] === null && !$type->allowsNull())
                throw new ParametersInvalidFormatException(sprintf(
                    'Parameter %s could not be cast to %s',
                    $parameter->name,
                    implode('|', $type_strings),
                ));
        }
        // execute with built args
        return call_user_func_array($callback, $args);
    }

    protected function getReflection(Closure $closure): ReflectionFunction
    {
        $id = spl_object_id($closure);
        return self::$reflection_cache[$id] ??= new ReflectionFunction($closure);
    }

    protected function buildArgumentObject(string $class, string $string, RouteContext $context): object|null
    {
        // autowire via FromStringInterface
        if (is_a($class, FromStringInterface::class, true))
            return $class::fromString($string);
        // try to find a matching factory callback
        foreach ($context->parameterFactoryCallbacks() as $factory_class => $factory)
            if (is_a($class, $factory_class, true)) {
                $result = $factory($string);
                if ($result !== null)
                    return $result;
            }

        // return null if we couldn't do it
        return null;
    }

    /**
     * Get all parameter factory callbacks defined in this router.
     * 
     * @return array<class-string,Closure(string):(object|null)>
     * @internal
     */
    public function parameterFactoryCallbacks(): array
    {
        return $this->parameter_factories;
    }

    /**
     * Convert a reflection type into an array of strings
     * 
     * @param ReflectionType|null $type
     * @return array<int,string>
     */
    protected function getTypeStrings(ReflectionType|null $type): array
    {
        if ($type === null)
            return [];
        if ($type instanceof ReflectionNamedType)
            return [$type->getName()];
        if ($type instanceof ReflectionUnionType) {
            $names = [];
            foreach ($type->getTypes() as $sub_type)
                foreach ($this->getTypeStrings($sub_type) as $type_string)
                    $names[] = $type_string;
            return $names;
        }
        return [];
    }

    /**
     * Sort type strings to put primitives at the end, in the order float > int > bool > string
     * 
     * @param array<int,string> $types
     * @return array<int,string>
     */
    protected function sortTypeStrings(array $types): array
    {
        if (in_array('float', $types)) {
            $types = array_diff($types, ['float']);
            $types[] = 'float';
        }
        if (in_array('int', $types)) {
            $types = array_diff($types, ['int']);
            $types[] = 'int';
        }
        if (in_array('bool', $types)) {
            $types = array_diff($types, ['bool']);
            $types[] = 'bool';
        }
        if (in_array('string', $types)) {
            $types = array_diff($types, ['string']);
            $types[] = 'string';
        }
        return $types;
    }

    /**
     * Normalize a single or array of guards to be an array of entirely Closures.
     * 
     * @param (callable(Request):(bool|null))|(Closure(never):(bool|null))|array<int, (Closure(never):(bool|null))|(callable(Request):(bool|null))> $guards
     * @return array<int,Closure(never):(bool|null)>
     */
    protected function normalizeGuards(callable|Closure|array $guards): array
    {
        $guards = is_array($guards) ? $guards : [$guards];
        $guards = array_map(
            // @phpstan-ignore-next-line phpstan just can't suss out what's going on here I guess
            fn(callable|Closure $c): Closure => $c instanceof Closure ? $c : Closure::fromCallable($c),
            $guards,
        );
        return array_values($guards);
    }

    /**
     * Normalize a single or array of middleware to be an array of entirely Closures.
     * 
     * @param (callable(never):(Response|RouteError))|(Closure(never):(Response|RouteError))|array<(Closure(never):(Response|RouteError))|(callable(never):(Response|RouteError))> $middleware
     * @return array<int,Closure(never):(Response|RouteError)>
     */
    protected function normalizeMiddleware(callable|Closure|array $middleware): array
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $middleware = array_map(
            // @phpstan-ignore-next-line phpstan just can't suss out what's going on here I guess
            fn(callable|Closure $m): Closure => $m instanceof Closure ? $m : Closure::fromCallable($m),
            $middleware,
        );
        return array_values($middleware);
    }

}
