# smolRouter

A lightweight, recursive PHP 8.2+ router built around pattern matching, explicit result types, and automatic parameter injection.

## Architecture overview

`smolRouter` 2.0 uses a very simple mental model, where everything is constructed as a unified **tree/node architecture**:

* **Recursive Routing:** Every `Router` is a node in a tree that can mount child routers that match on whatever remaining path their parents didn't match.
* **Explicit Execution Pipeline:** `Router::run()` returns either a `Response` or a `RouteError`. There are no thrown exceptions for control flow, and all thrown exceptions are converted to `RouteError` objects.
* **Typed Parameter Injection:** Route parameters, reserved variables, and custom objects are injected into guards and handlers automatically.

## Installation

```bash
composer require joby-lol/smol-router
```

## Basic usage

A `Router` matches an incoming path segment and executes its handler if the request matches the full remaining path string.

```php
use Joby\Smol\Router\Router;
use Joby\Smol\Response\Response;
use Joby\Smol\Response\Status;

$router = new Router(
    pattern: 'about/',
    handler: fn() => new Response(new Status(200)),
);

$responseOrError = $router->run($request);
```

## Route context and path matching

The strings extracted from the `Request` object's path do not include leading slashes for pattern matching (`/about/` becomes `about/`).

* Patterns use `:name` tokens to capture dynamic segments.
* Matching consumes path segments and passes the remaining path down to child nodes.

```php
$router = new Router(
    pattern: 'users/:id/',
    handler: function (int $id) {
        // Automatically converts string parameter to integer
    },
);
```

## HTTP methods

By default, routes accept `GET` and `POST`. Restrict methods by passing single `Method` enum values or arrays. Route restrictions apply to all child Routers as well, and children can only further restrict methods, not expand them.

```php
use Joby\Smol\Request\Method;

$router = new Router(
    pattern: 'submit/',
    handler: fn() => new Response(new Status(200)),
    method: Method::POST,
);
```

If a path matches but the request method is disallowed, `Router::run()` immediately returns a hard `405 RouteError`.

## Sub-router traversals

Tree structures are built by mounting child `Router` instances.

```php
$root = new Router(pattern: 'api/');

$users = new Router(
    pattern: 'users/:id/',
    handler: fn(int $id) => new Response(new Status(200)),
);

$root->addRouter($users);
```

### Route Resolution & Error Handling Rules

1. **Responses Win:** The first child to return a valid `Response` completes routing.
2. **Hard Short-Circuiting:** If any node triggers a hard failure (HTTP status `403`, `>= 500`, or an uncaught exception), processing halts immediately and returns that `RouteError`.
3. **Soft Error Fallthrough:** Soft failures (such as `404` pattern misses, `405` method mismatches, or type conversion failures) are stashed. If no child yields a `Response`, the first soft error encountered is returned.

## Parameter Injection & Autowiring

Handlers and guards support automatic argument resolution by parameter name and type.

### Reserved Parameters

* `$request` (`Joby\Smol\Request\Request`): The original request object.
* `$remaining_path` (`string`): Path remaining after current node matching.
* `$all_parameters` (`array<string,string>`): Raw string map of all matched pattern parameters in their raw string form.

### Primitive casting and union types

URL parameter strings are automatically cast to primitive types (`int`, `float`, `bool`, `string`).

```php
$router = new Router(
    pattern: 'items/:id/:active/',
    handler: function (int $id, bool $active) {
        // $id is int, $active is bool
    },
);
```

When type-hinted with union types (e.g., `int|string`), parameter casting evaluates types from most specific to broadest (`object` > `float` > `int` > `bool` > `string`).

### Custom Parameter Autowiring

Classes implementing `FromStringInterface` are autowired automatically:

```php
use Joby\Smol\Router\FromStringInterface;

class UserID implements FromStringInterface
{
    public static function fromString(string $value): static|null
    {
        return is_numeric($value) ? new static($value) : null;
    }
}

// Injected automatically without explicit registration
$router = new Router(
    pattern: 'users/:id/',
    handler: fn(UserID $id) => /* ... */,
);
```

Custom factories can also be registered per `Router` node:

```php
$router->addParameterFactory(
    User::class,
    fn(string $id) => UserRepository::find($id),
);
```

Child routers inherit parameter factories from parent routers. Both will be attempted, starting with the child node and working backwards.

## Guards

Guards provide access control and run prior to handler or child execution.

```php
use Joby\Smol\Router\Priority;

$router->guard(
    callback: function (Request $request): ?bool {
        return $request->headers->has('Authorization') ? true : false;
    },
    priority: Priority::HIGH,
);
```

### Guard return values

* `true`: Access granted. Short-circuits remaining guards on this node.
* `false`: Access denied. Immediately returns a hard `403 RouteError`.
* `null`: Abstains. Continues evaluating remaining guards.

## Requirements

Fully tested on PHP 8.3+, static analysis for PHP 8.2+.

## License

MIT License - See [LICENSE](LICENSE) file for details.