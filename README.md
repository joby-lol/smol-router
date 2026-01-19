# smolRouter

A lightweight PHP router with flexible matching strategies and automatic parameter injection.

## Installation

```bash
composer require joby-lol/smol-router
```

## About

This router uses [smolRequest](https://github.com/joby-lol/smol-request) for handling HTTP requests and [smolResponse](https://github.com/joby-lol/smol-response) for building responses. Route handlers receive `Request` objects and return `Response` objects from these libraries.

## Basic Usage

Adding new routes with the `Router::add()` method will make them available for both `GET` and `POST` requests.

```php
use Joby\Smol\Router\Router;
use Joby\Smol\Router\Matchers\ExactMatcher;
use Joby\Smol\Response\Response;
use Joby\Smol\Response\Status;

$router = new Router();

// will match both GET and POST requests by default
$router->add(
    new ExactMatcher('about'),
    fn() => new Response(new Status(200))
);

$response = $router->run($request);
```

## Request methods

Routes can be limited to common request methods using `Router::get()`, `Router::post()`, etc.

```php
use Joby\Smol\Router\Router;
use Joby\Smol\Router\Matchers\ExactMatcher;
use Joby\Smol\Response\Response;
use Joby\Smol\Response\Status;

$router = new Router();

// will only match GET requests
$router->get(
    new ExactMatcher('about'),
    fn() => new Response(new Status(200))
);

$response = $router->run($request);
```

You can also specify your own list of request methods via `Router::add()` by passing values from the `Method` enum.

```php
use Joby\Smol\Router\Router;
use Joby\Smol\Router\Matchers\ExactMatcher;
use Joby\Smol\Response\Method;
use Joby\Smol\Response\Response;
use Joby\Smol\Response\Status;

$router = new Router();

// will only match PATCH and PUT requests
$router->get(
    new ExactMatcher('about'),
    fn() => new Response(new Status(200)),
    method: [Method::PATCH, Method::PUT]
);

$response = $router->run($request);
```

## Matchers

### ExactMatcher

Matches a specific path exactly.

```php
$router->add(
    new ExactMatcher('about'),
    fn() => new Response(new Status(200))
);
// Matches: about
// Doesn't match: about/team, contact
```

### PatternMatcher

Matches paths with named parameters.

```php
$router->add(
    new PatternMatcher('users/:id'),
    fn(string $id) => new Response(new Status(200))
);
// Matches: users/123, users/abc
// Doesn't match: users, users/123/posts

$router->add(
    new PatternMatcher('posts/:post_id/comments/:comment_id'),
    fn(string $post_id, string $comment_id) => /* ... */
);
// Matches: posts/456/comments/789
```

### CatchallMatcher

Matches any path.

```php
$router->add(
    new CatchallMatcher(),
    fn() => new Response(new Status(404))
);
```

### PrefixMatcher

Matches any path starting with a prefix. By default, captures the remainder after the prefix as `prefix_remainder`.

```php
// Basic usage - captures remainder automatically
$router->add(
    new PrefixMatcher('api/'),
    fn(string $prefix_remainder) => /* ... */
);
// Matches: api/users → $prefix_remainder = 'users'
// Matches: api/posts/123 → $prefix_remainder = 'posts/123'

// Custom parameter name
$router->add(
    new PrefixMatcher('files/', 'path'),
    fn(string $path) => /* ... */
);
// Matches: files/document.pdf → $path = 'document.pdf'

// Disable capture
$router->add(
    new PrefixMatcher('api/', null),
    fn() => /* ... */
);
```

#### Composing with Other Matchers

Use the `with()` method to compose a PrefixMatcher with another matcher. The child matcher will be matched against the remainder after the prefix.

```php
// Match api/users/:id pattern
$api = new PrefixMatcher('api/v1/');
$router->add(
    $api->with(new PatternMatcher('users/:id')),
    fn(int $id, string $prefix_remainder) => /* ... */
);
// Matches: api/v1/users/123 → $id = 123, $prefix_remainder = 'users/123'
```

#### Reusable Matcher Patterns

Define a prefix once and compose it with multiple patterns:

```php
$apiV1 = new PrefixMatcher('api/v1/');

$router->add(
    $apiV1->with(new PatternMatcher('users/:id')),
    fn(int $id) => /* handle user */
);

$router->add(
    $apiV1->with(new PatternMatcher('posts/:id')),
    fn(int $id) => /* handle post */
);

$router->add(
    $apiV1->with(new SuffixMatcher('.json')),
    fn() => /* handle JSON endpoints */
);
```

### SuffixMatcher

Matches paths ending with a suffix. By default, captures the base path before the suffix as `suffix_base`.

```php
// Basic usage - captures base automatically
$router->add(
    new SuffixMatcher('.json'),
    fn(string $suffix_base) => /* ... */
);
// Matches: users.json → $suffix_base = 'users'
// Matches: posts/123.json → $suffix_base = 'posts/123'

// Custom parameter name
$router->add(
    new SuffixMatcher('.json', 'resource'),
    fn(string $resource) => /* ... */
);
// Matches: users.json → $resource = 'users'

// Disable capture
$router->add(
    new SuffixMatcher('.json', null),
    fn() => /* ... */
);
```

#### Composing with Other Matchers

Use the `with()` method to compose a SuffixMatcher with another matcher. The child matcher will be matched against the base path before the suffix.

```php
// Match users/:id.json pattern
$json = new SuffixMatcher('.json');
$router->add(
    $json->with(new PatternMatcher('users/:id')),
    fn(int $id, string $suffix_base) => /* ... */
);
// Matches: users/123.json → $id = 123, $suffix_base = 'users/123'
```

#### Reusable Matcher Patterns

Define a suffix once and compose it with multiple patterns:

```php
$json = new SuffixMatcher('.json');

$router->add(
    $json->with(new PatternMatcher('users/:id')),
    fn(int $id) => /* handle user as JSON */
);

$router->add(
    $json->with(new PatternMatcher('posts/:id')),
    fn(int $id) => /* handle post as JSON */
);

$router->add(
    $json->with(new PrefixMatcher('api/v1/')),
    fn() => /* handle API v1 JSON endpoints */
);
```

### TransformerMatcher

Transforms a path before matching it against a child matcher. This is useful for preprocessing paths (e.g., lowercasing for case-insensitive matching, normalizing formats, removing query strings). By default, captures the original untransformed path as `original_path`.

The transformer function can also reject a match by returning `null`.

```php
// Case-insensitive matching
$lowercase = new TransformerMatcher(fn(string $p) => strtolower($p));
$router->add(
    $lowercase->with(new ExactMatcher('about')),
    fn(string $original_path) => /* ... */
);
// Matches: 'ABOUT', 'About', 'about' → $original_path = original casing

// Remove query strings
$router->add(
    new TransformerMatcher(fn(string $p) => explode('?', $p)[0])
        ->with(new ExactMatcher('search')),
    fn() => /* ... */
);
// Matches: 'search?q=test', 'search?filter=all'

// Custom parameter name for original path
$router->add(
    new TransformerMatcher(fn($p) => strtolower($p), 'raw_path')
        ->with(new PatternMatcher('users/:id')),
    fn(int $id, string $raw_path) => /* ... */
);
// Matches: 'USERS/123' → $id = 123, $raw_path = 'USERS/123'

// Disable capturing original path
$router->add(
    new TransformerMatcher(fn($p) => urldecode($p), null)
        ->with(new PatternMatcher('search/:query')),
    fn(string $query) => /* ... */
);
```

#### Reusable Transformer Patterns

Define a transformer once and compose it with multiple matchers:

```php
$lowercase = new TransformerMatcher(fn(string $p) => strtolower($p));

$router->add(
    $lowercase->with(new ExactMatcher('about')),
    fn() => /* handle about page */
);

$router->add(
    $lowercase->with(new ExactMatcher('contact')),
    fn() => /* handle contact page */
);

$router->add(
    $lowercase->with(new PrefixMatcher('api/')),
    fn(string $prefix_remainder) => /* handle API routes case-insensitively */
);
```

#### Transformer Rejection

Transformers can reject matches by returning `null`:

```php
// Only match paths that start with 'allowed'
$filtered = new TransformerMatcher(function(string $p) {
    return str_starts_with($p, 'allowed') ? $p : null;
});

$router->add(
    $filtered->with(new PrefixMatcher('allowed')),
    fn() => /* only paths starting with 'allowed' can match */
);
```

## Parameter Injection

Handler functions automatically receive parameters extracted by matchers, with type conversion. If a type cannot be created from the string form of the parameter an exception will be thrown, and the user will get a 400 error indicating that it was an invalid request.

```php
// String parameters
$router->add(
    new PatternMatcher('users/:id'),
    fn(string $id) => /* $id is a string */
);

// Typed parameters (automatically converted)
$router->add(
    new PatternMatcher('users/:id'),
    fn(int $id) => /* $id is converted to int */
);

// Multiple parameters
$router->add(
    new PatternMatcher('posts/:id/page/:page'),
    fn(int $id, int $page) => /* both converted to int */
);

// Request injection
// To get the full Request object, add a typed parameter named "$request"
$router->add(
    new ExactMatcher('info'),
    fn(Request $request) => /* receives the full request object */
);

// Path injection
// To get the full matched path, add a string parameter called "$path"
$router->add(
    new PatternMatcher('users/:id'),
    fn(string $path, int $id) => /* $path = 'users/123', $id = 123 */
);
```

## HTTP Methods

Restrict routes to specific HTTP methods. By default all routes can match both GET and POST requests.

```php
use Joby\Smol\Request\Method;

$router->add(
    new ExactMatcher('login'),
    fn() => /* ... */,
    Method::POST
);

$router->add(
    new PatternMatcher('users/:id'),
    fn(int $id) => /* ... */,
    [Method::GET, Method::HEAD]
);
```

## Priorities

Control route matching order with priorities. By default routes are added with "normal" priority.

```php
use Joby\Smol\Router\Priority;

// Checked first
$router->add(
    new ExactMatcher('special'),
    fn() => /* ... */,
    priority: Priority::HIGH
);

// Checked second (default)
$router->add(
    new PrefixMatcher('api/'),
    fn() => /* ... */,
    priority: Priority::NORMAL
);

// Checked last
$router->add(
    new CatchallMatcher(),
    fn() => /* ... */,
    priority: Priority::LOW
);
```

## Guards

Guards provide access control that runs before route handlers. They're evaluated in priority order and can allow, deny, or defer the decision to continue processing.

```php
use Joby\Smol\Router\Priority;

// Basic authentication guard
$router->guard(
    new PrefixMatcher('admin/'),
    function (Request $request): bool|null {
        if (!isset($_SESSION['user'])) {
            return false; // Deny access (403)
        }
        return null; // Allow further processing
    }
);

// Role-based access control
$router->guard(
    new PrefixMatcher('admin/'),
    function (): bool {
        return $_SESSION['role'] === 'admin'; // true = allow, false = deny
    },
    priority: Priority::HIGH
);

// Guards support the same parameter injection as handlers
$router->guard(
    new PatternMatcher('users/:id/edit'),
    function (int $id, Request $request): bool|null {
        $currentUser = $_SESSION['user_id'] ?? null;
        if ($currentUser !== $id) {
            return false; // Users can only edit their own profile
        }
        return null;
    }
);
```

### Guard Return Values

- `null` - Continue processing (allow access if no other guards deny)
- `false` - Deny access immediately (returns 403 Forbidden)
- `true` - Allow access immediately (skip remaining guards)

### Guard HTTP Method Filtering

By default guards run on all HTTP methods, but can be limited to specific methods:

```php
// Only guard POST requests
$router->guard(
    new PrefixMatcher('api/'),
    fn() => checkCsrfToken() ? null : false,
    method: Method::POST
);

// Guard multiple methods
$router->guard(
    new PrefixMatcher('admin/'),
    fn() => isAuthenticated() ? null : false,
    method: [Method::POST, Method::PUT, Method::DELETE]
);
```

### Guard Priority

Guards are executed in priority order (HIGH → NORMAL → LOW). Processing stops at the first non-null return value.

```php
// Check authentication first
$router->guard(
    new CatchallMatcher(),
    fn() => isAuthenticated() ? null : false,
    priority: Priority::HIGH
);

// Then check specific permissions
$router->guard(
    new PrefixMatcher('admin/'),
    fn() => hasAdminRole() ? null : false,
    priority: Priority::NORMAL
);
```

## Response Modifiers

Modifiers alter responses after they're generated by route handlers. They run in priority order and can modify any response, including error pages. They can also have matcher results injected the same as route handlers.

```php
// Add CORS headers to all API responses
$router->modify(
    new PrefixMatcher('api/'),
    function (Response $response): Response {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }
);

// Convert objects to JSON
$router->modify(
    new SuffixMatcher('.json'),
    function (Response $response): Response {
        if ($response->content instanceof MyDataObject) {
            $json = json_encode($response->content->toArray());
            $response->setContent(new StringContent($json));
            $response->headers->set('Content-Type', 'application/json');
        }
        return $response;
    }
);

// Modifiers also receive matched parameters
$router->modify(
    new PatternMatcher('api/:version'),
    function (string $version, Response $response): Response {
        $response->headers->set('API-Version', $version);
        return $response;
    }
);
```

### Modifier Return Values

- `null` - Keep the original response unchanged
- `Response` - Replace the response with the returned one
- `FinalResponse` - Replace the response and skip all remaining modifiers

```php
// Conditional modification
$router->modify(
    new CatchallMatcher(),
    function (Response $response): Response|null {
        if ($response->status->code >= 400) {
            // Modify error responses
            return createCustomErrorPage($response);
        }
        return null; // Leave successful responses unchanged
    }
);
```

### Modifier Execution

Modifiers run in priority order (HIGH → NORMAL → LOW) and unless a FinalResponse is returned all of them will be run:

```php
// First modifier (runs first)
$router->modify(
    new CatchallMatcher(),
    function (Response $response): Response {
        $response->headers->set('X-Powered-By', 'smolRouter');
        return $response;
    },
    priority: Priority::HIGH
);

// Second modifier (runs after the first)
$router->modify(
    new CatchallMatcher(),
    function (Response $response): Response {
        $response->headers->set('X-Request-ID', uniqid());
        return $response;
    },
    priority: Priority::NORMAL
);
```

### Modifier HTTP Method Filtering

By default modifiers run on all HTTP methods, but can be limited to specific methods:

```php
// Only modify POST responses
$router->modify(
    new PrefixMatcher('api/'),
    fn(Response $response) => addCreatedTimestamp($response),
    method: Method::POST
);
```

### When Modifiers Run

Modifiers execute for:
- Successful route matches (200, 201, etc.)
- Error responses (404, 500, etc.)
- Any response generated by the router

Modifiers do **not** run when:
- A handler returns a `FinalResponse`
- A higher-priority modifier returns a `FinalResponse`
- An exception is thrown during path extraction or normalization

## Type Handlers

Register custom type handlers for parameter conversion.

```php
$router->typeHandler(DateTime::class, function (string $value): ?DateTime {
    try {
        return new DateTime($value);
    } catch (Exception $e) {
        return null;
    }
});

$router->add(
    new PatternMatcher('events/:date'),
    fn(DateTime $date) => /* $date is a DateTime object */
);
```

## Error Handling

### Exception Handlers

Convert exceptions into HTTP errors by type. A handful of defaults are provided that will convert most exceptions into either 400 or 500 responses.

```php
$router->exceptionClassHandler(
    MyCustomException::class,
    fn(MyCustomException $e) => new HttpException(400, 'Bad request', $e)
);
```

### Error Page Builders

Customize error responses by status code. Default implementation makes error pages that are simple txt files.

```php
// Specific status code
$router->errorPageBuilder('404', function (HttpException $e) {
    $response = new Response($e->status);
    $response->setContent(new StringContent('Page not found'));
    return $response;
});

// Wildcard patterns
$router->errorPageBuilder('4xx', fn(HttpException $e) => /* ... */);
$router->errorPageBuilder('40x', fn(HttpException $e) => /* ... */);

// Default fallback
$router->errorPageBuilder('default', fn(HttpException $e) => /* ... */);
```

## Route Normalization

By default, routes are normalized to have no leading or trailing slashes, and the root path is represented as an empty string. You can add custom normalization that runs before the default normalization.

```php
// Convert to lowercase
$router->routeNormalizer(fn(string $route) => strtolower($route));

// The built-in normalization (trim slashes) runs after custom normalization
```

## Route Extraction

Customize how routes are extracted from requests. By default the path used for routing is the full path string from the request's URL object.

```php
// Extract from a subdirectory
$router->routeExtractor(function (Request $request): string {
    $path = $request->url->path->__toString();
    return preg_replace('#^myapp/#', '', $path);
});
```

## Requirements

Fully tested on PHP 8.3+, static analysis for PHP 8.1+.

## License

MIT License - See [LICENSE](LICENSE) file for details.