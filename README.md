![jasny-banner](https://user-images.githubusercontent.com/100821/62123924-4c501c80-b2c9-11e9-9677-2ebc21d9b713.png)

SwitchRoute
===

[![Build Status](https://github.com/jasny/switch-route/actions/workflows/php.yml/badge.svg)](https://github.com/jasny/switch-route/actions/workflows/php.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/switch-route/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/switch-route/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/jasny/switch-route/master)](https://infection.github.io)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)
[![Packagist License](https://img.shields.io/packagist/l/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)

Generating a PHP script for faster routing.

The traditional way of routing uses regular expressions. This method was improved by FastRoute, which compiles
all routes to a single regexp. **SwitchRoute** abandons this completely, opting for a series of `switch` statements
instead.

Processing the routes to produce the switch statements isn't particularly fast. However, the generation only need to
happen when the routes change. Routing using the generated switch statements is up to 2x faster than with FastRoute
using caching and up to 100x faster than any router not using caching.

```
============================= Average Case (path) =============================

SwitchRoute            100% | ████████████████████████████████████████████████████████████  |
FastRoute (cache)       59% | ███████████████████████████████████                           |
SwitchRoute (psr)       20% | ███████████                                                   |
Symfony                  2% | █                                                             |
FastRoute                1% |                                                               |
Laravel                  1% |                                                               |
```

**[See all benchmark results](https://github.com/jasny/php-router-benchmark)**

Installation
---

    composer require jasny/switch-route

Requires PHP 7.2+

Usage
---

In all examples we'll use the following function to get the routes;

```php
function getRoutes(): array
{
    return [
        'GET      /'                  => ['controller' => 'InfoController'],

        'GET      /users'             => ['controller' => 'UserController', 'action' => 'listAction'],
        'POST     /users'             => ['controller' => 'UserController', 'action' => 'addAction'],
        'GET      /users/{id}'        => ['controller' => 'UserController', 'action' => 'getAction'],
        'POST|PUT /users/{id}'        => ['controller' => 'UserController', 'action' => 'updateAction'],
        'DELETE   /users/{id}'        => ['controller' => 'UserController', 'action' => 'deleteAction'],

        'GET      /users/{id}/photos' => ['action' => 'ListPhotosAction'],
        'POST     /users/{id}/photos' => ['action' => 'AddPhotosAction'],

        'POST     /export'            => ['include' => 'scripts/export.php'],
    ];
}
```

Both `{id}` and `:id` syntax are supported for capturing url path variables. If the segment can be anything, but
doesn't need to be captured, use `*` (eg `/comments/{id}/*`).

Regular expressions on path variables (eg `{id:\d+}`) is **not** supported.

The path variables can be used arguments when invoking the action. Reflection is used to determine the name of the
parameters, which are matched against the names of the path variables.

```php
class UserController
{
    public function updateAction(string $id)
    {
        // ...
    }
}
```

_Note that the path variables are always strings._

#### Pretty controller and action names

By default the `controller` and `action` should be configured with a fully qualified class name (includes namespace).
However, it's possible to use a pretty name instead and have the `Invoker` convert it to an fqcn.

```php
function getRoutes(): array
{
    return [
        'GET      /'                  => ['controller' => 'info'],

        'GET      /users'             => ['controller' => 'user', 'action' => 'list'],
        'POST     /users'             => ['controller' => 'user', 'action' => 'add'],
        'GET      /users/{id}'        => ['controller' => 'user', 'action' => 'get'],
        'POST|PUT /users/{id}'        => ['controller' => 'user', 'action' => 'update'],
        'DELETE   /users/{id}'        => ['controller' => 'user', 'action' => 'delete'],

        'GET      /users/{id}/photos' => ['action' => 'list-photos'],
        'POST     /users/{id}/photos' => ['action' => 'add-photos'],

        'POST     /export'            => ['include' => 'scripts/export.php'],
    ];
}
```

Pass a callable to the `Invoker` that converts the pretty controller and action names 

```php
$stud = fn($str) => strtr(ucwords($str, '-'), ['-' => '']);
$camel = fn($str) => strtr(lcfirst(ucwords($str, '-')), ['-' => '']);

$invoker = new Invoker(function (?string $controller, ?string $action) use ($stud, $camel) {
    return $controller !== null
        ? [$stud($controller) . 'Controller', $camel($action ?? 'default') . 'Action']
        : [$stud($action) . 'Action', '__invoke'];
});
```

### Basic

By default the generator generates a function to route requests.

```php
use Jasny\SwitchRoute\Generator;

// Always generate in development env, but not in production.
$overwrite = (getenv('APPLICATION_ENV') ?: 'dev') === 'dev';

$generator = new Generator();
$generator->generate('route', 'generated/route.php', 'getRoutes', $overwrite);
```

To route, include the generated file and call the `route` function.

```php
require 'generated/route.php';

$method = $_SERVER["REQUEST_METHOD"];
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

route($method, $path);
```

### PSR-15 compatible middleware

[PSR-7](https://www.php-fig.org/psr/psr-7/) is an abstraction for HTTP Requests. It allows you to more easily test your
application. It's available through [http_message extension from pecl](https://pecl.php.net/package/http_message) or one
of the many PSR-7 libraries.

The related [PSR-15](https://www.php-fig.org/psr/psr-15/) describes a way of processing `ServerRequest` objects through
handlers and middleware. This makes it easier to abstract your application.

The library can generate a classes that implements [PSR-15](https://www.php-fig.org/psr/psr-15/) `MiddlewareInterface`.

This generated route middleware will set the attributes of the `ServerRequest` based on the matched route. The generated
invoke middleware will instantiate the controller and invoke the action.

This has been split into two steps, so you can add middleware that acts after the route is determined, but before it's
invoked.

```php
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;

// Always generate in development env, but not in production.
$overwrite = (getenv('APPLIACTION_ENV') ?: 'dev') === 'dev';

$routeGenerator = new Generator(new Generator\GenerateRouteMiddleware());
$routeGenerator->generate('App\Generated\RouteMiddleware', 'generated/RouteMiddleware.php', 'getRoutes', $overwrite);

$invoker = new Invoker();
$invokeGenerator = new Generator(new Generator\GenerateInvokeMiddleware($invoker));
$invokeGenerator->generate('App\Generated\InvokeMiddleware', 'generated/InvokeMiddleware.php', 'getRoutes', $overwrite);
```

Use any PSR-15 compatible request dispatcher, like [Relay](https://relayphp.com/), to handle the request.

```php
use App\Generated\RouteMiddleware;
use App\Generated\InvokeMiddleware;
use Jasny\SwitchRoute\NotFoundMiddleware;
use HttpMessage\Factory as HttpFactory;
use HttpMessage\ServerRequest;
use Relay\Relay;

$httpFactory = new HttpFactory();

$middleware[] = new RouteMiddleware();
$middleware[] = new NotFoundMiddleware($httpFactory);
$middleware[] = new InvokeMiddleware(fn($controllerClass) => new $controllerClass($httpFactory));

$relay = new Relay($middleware);

$request = new ServerRequest($_SERVER, $_COOKIE, $_QUERY, $_POST, $_FILES);
$response = $relay->handle($request);
```

_You typically want to use a DI (dependency injection) container, optionally with autowiring, to create a controller
rather than doing a simple `new`._

### Error pages

If there is no route that matches against the current request URI, the generated script or invoker will give a
`404 Not Found` or `405 Method Not Allowed` response. The `405` response given, when there _is_ a matching endpoint,
but none of the methods match. The response will contain a simple text body.

To change this behavior create a `default` route.

```php
function getRoutes(): array
{
    return [
        'GET /'   => ['controller' => 'info'],
        // ...

        'default' => ['controller' => 'error', 'action' => 'not-found],
    ];
}
```

If the method should have `array $allowedMethods` as function parameter. If the array is empty, a `404 Not Found`
response should be given. Otherwise a `405 Method Not Allowed` response may be given, including an `Allow` header with
the allowed methods.  

```php
class ErrorController
{
    public function notFoundAction(array $allowedMethods)
    {
        if ($allowedMethods === []) {
            http_response_code(404);
        } else {
            http_response_code(405);
            header('Allow: ' . join(', ', $allowedMethods));
        }
        
        echo "<h1>Sorry, there is nothing here</h1>";
    }
}
```

_This example shows a simple implementation that doesn't use the PSR-7 ServerRequest._

### Pre-generated routing script

If the generated file already exists, the overhead of `SwitchRoute` is already minimal. To have zero overhead  in a
production environment, generate the classes or script in advance each time a new version is deployed.

Create a script `bin/generate-router.php`

```php
require_once 'config/routes.php';

(new Jasny\SwitchRoute\Generator)->generate('route', 'generated/route.php', 'getRoutes', true);
```

Add it to `composer.json` so it's called every time after autoload is updated, which occurs by running `composer update`
or `composer install`.

```json
{
    "scripts": {
        "post-autoload-dump": [
            "bin/generate-router.php"
        ]
    }
}
```

Documentation
---

### Generator

`Generator` is a service to generate a PHP script based on a set of routes.

Each route is a key pair, where the key is the HTTP method and URL path. The value is an array that should contain
either a `controller` and (optionally) an `action` property OR an `include` property.

For routes with an `include` property, the script simply gets included. Using `include` provides a way to add routing
to legacy applications.

For routes with a `controller` property, the controller will be instantiated and the action will be invoked, using
parameter parsed from the URL as arguments.

The generator takes a callable as construction argument, which is used to generated the PHP code from structured routes.
This is one the invokable `Generator\Generate...` objects from this library or a
[custom generation function](#customization).

```php
new Generator(new Generator\GenerateRouteMiddleware());
```

A new `GenerateFunction` object is created if no generate callable is supplied (optional DI).

The class has a single method `generate()` and no public properties.

#### Generator::generate()

`Generator::generate()` will create a PHP script to route a request. The script is written to the specified file, which
should be included via `require` (or autoload).

    Generator::generate(string $name, string $filename, callable $getRoutes, bool $force)

The `$name` is either the name of the function or the name of the class being generated and may include a namespace.

Rather than passing the routes as argument, a callback is used which is called to get the routes. This callback isn't
invoked if the router is not replaced. 

The optional `force` parameter defaults to `true`, meaning that a new script is generated each time that this method is
called. In a production environment, you should set this to `false` and delete the generated files each time you update
your application.

If `force` is `false` and the file already exists, no new file is generated. It's recommended to have the `opcache`
zend extension installed and enabled. This prevents additional checks on the file system for each request.

### Generator\GenerateFunction

`GenerateFunction` is an invokable class which generated as function that will call the action or include the script
specified by the route.

This class doesn't use PSR-7 or any other request abstraction. Instead it will directly instantiate the controller and
invoke the action, passing the correct path segments as arguments.

You should pass an `Invoker` object when instantiating this invokable. If you don't, one will automatically be created
during construction (optional DI).

When calling `generate`, you pass the name of the routing function. This may include a namespace.

```php
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;

$invoker = new Invoker();
$generate = new Generator\GenerateFunction($invoker);

$generator = new Generator($generate);
$generator->generate('route', 'generated/route.php', 'getRoutes', true);
```

The generated function takes two arguments, first is the request method and second is the request path. The path isn't
directly available in `$_SERVER`, but needs to be extracted from `$_SERVER['REQUEST_URI']` which also contains the query
string.

```php
require 'generated/route.php';

$method = $_SERVER["REQUEST_METHOD"];
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

route($method, $path);
```

The function returns whatever is returned by the action method.

### Generator\GenerateRouteMiddleware

`Generator\GenerateRouteMiddleware` is an invokable to generate a middleware class that will determine the route and set
both the route properties and path variables as PSR-7 `ServerRequest` attributes. The middleware implements the PSR-15
`MiddlewareInterface`.

When calling `generate`, you pass the name of the middleware class. This may include a namespace.

```php
use Jasny\SwitchRoute\Generator;

$generate = new Generator\GenerateRouteMiddleware();

$generator = new Generator($generate);
$generator->generate('RouteMiddleware', 'generated/RouteMiddleware.php', 'getRoutes', true);
```

The route is determined only using `$request->getMethod()` and `$request->getUri()->getPath()`.

The middleware will take the attributes of the route, like `controller` and `action` and add them to the
`ServerRequest` via `$request->addAttribute()`. The names are prefixed with `route:` to prevent potential collisions
with other middleware. So `action` becomes attribute `route:action`.

Beyond the attributes required to invoke the action, additional attributes may be specified, which can be handled by
custom middleware or by the controller. For instance something like `auth` containing the required an authorization
level. Do note that these attributes are always prefixed with `route:`, so `auth` becomes `route:auth`.

Path variables are formatted as `route:{...}` (eg `route:{id}`). This means that they won't collide with route
arguments. To specify a fixed value for an argument of the action, the route argument need to have these braces.

```php
[
    'GET /users/{id}/photos'        => ['action' => 'ListPhotosAction', '{page}' => 1],
    'GET /users/{id}/photos/{page}' => ['action' => 'ListPhotosAction'],
];
```

The middleware will set the `route:methods_allowed` attribute if it matches the URL path, regardless of the HTTP
request method. This is many useful for responding with `405 Method Not Allowed`.

### Generator\GenerateInvokeMiddleware

`Generator\GenerateInvokeMiddleware` is an invokable to generate a middleware class that invokes the action based on the
`ServerRequest` attributes. The middleware implements the PSR-15 `MiddlewareInterface`.

You should pass an `Invoker` object when instantiating this invokable. If you don't, one will automatically be created
during construction (optional DI).

When calling `generate`, you pass the name of the middleware class. This may include a namespace.

```php
use Jasny\SwitchRoute\Generator;

$generate = new Generator\GenerateInvokeMiddleware();

$generator = new Generator($generate);
$generator->generate('InvokeMiddleware', 'generated/InvokeMiddleware.php', 'getRoutes', true);
```

The generated class takes a callable as single, optional, constructor argument. This callable is used to instantiate
controllers or actions and allows you to do dependency injection. When omitted, a simple `new` statement is used.

The invoke middleware should be used after the generated route middleware. Other (custom) middleware may be added
between these two, which can act based on the server request attributes set by the route middleware.

Note that all controller and action calls are generated at forehand. Request attributes like `route:controller` and
`route:action` should not be modified.

For routes that specify an `include` attribute, the script will simply be included and no other method calls are made.

Either a default route should be specified or `NotFoundMiddleware` should used. If neither is done, the generated
invoke middleware will throw a `LogicException` when there is no matching route.

### NotFoundMiddleware

`NotFoundMiddlware` will give a `404 Not Found` or `405 Method Not Allowed` response if there is no matching route and
no default route has been specified.

The middleware takes an object that implements [PSR-17](https://www.php-fig.org/psr/psr-17/) `ResponseFactoryInterface`
as constructor argument. This factory is used to create a response when there is no matching route. 

Attribute `route:allowed_methods` determines the response code. If there are no allowed methods for the URL, a `404`
response is given. If there are, a `405` is given instead.

The generated response will have a simple text body with the HTTP status reason phrase. In case of a `405` response,
the middleware will also set the `Allow` header.

### Invoker

The `Invoker` is generates snippets for invoking the action or including the file as stated in the selected route. This
includes converting the `controller` and/or `action` attribute to a class name and possibly method name.

By default, the if the route has a `controller` property, use it as the class name. The `action` property is taken as
method. It defaults to `defaultAction`.

If only an `action` property is present, the invoker will use that as class name. The class must define an
[invokable object](https://www.php.net/manual/en/language.oop5.magic.php#object.invoke).

You can change how the invokable class and method names are generated by passing a callback to the constructor. The can
be used for instance convert pretty names to fully qualified class names (FQCN) for the the controller and action class.

```php
$stud = fn($str) => strtr(ucwords($str, '-'), ['-' => '']);
$camel = fn($str) => strtr(lcfirst(ucwords($str, '-')), ['-' => '']);

$invoker = new Invoker(function (?string $controller, ?string $action) use ($stud, $camel) {
    return $controller !== null
        ? ['App\\' . $stud($controller) . 'Controller', $camel($action ?? 'default') . 'Action']
        : ['App\\' . $stud($action) . 'Action', '__invoke'];
});
```

The invoker uses reflection to determine if the method is static or not. If the method isn't static the cont

Reflection is also used to find out the names of the arguments of the invokables. Those names are matched with the names
of the path variables (like `{id}` => `$id`).

### NoInvoker

`NoInvoker` can be used instead of `Invoker` to return the matched route, rather than invoking the action. The purpose
is mainly for benchmarking and testing.

This should only be used in combination with `GenerateFunction`. When using `PSR-7`, you can achieve something similar
by only using route middleware and not invoke middleware.

```php
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\NoInvoker;

$invoker = new NoInvoker();
$generate = new Generator\GenerateFunction($invoker);

$generator = new Generator($generate);
$generator->generate('route', 'generated/route.php', 'getRoutes', true);
```

The result of the generated function is an array with 3 elements. The first contains the HTTP status, the second
contains the route attributes and the third hold the path variables.

```php
require 'generated/route.php';

$method = $_SERVER["REQUEST_METHOD"];
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

$routeInfo = route($method, $path);

switch ($routeInfo[0]) {
    case 404:
        // ... 404 Not Found
        break;
    case 405:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    case 200:
        $route = $routeInfo[1];
        $vars = $routeInfo[2];
        // ... invoke action based on $route using $vars
        break;
}
```

## Customization

You may pass any callable when creating a `Generator` class. This callable should have the following signature;

```php
use Jasny\SwitchRoute\Generator;

$generate = function (string $name, array $routes, array $structure): string {
    // ... custom logic
    return $generatedCode;
};

$generator = new Generator($generate);
$generator->generate('route', 'generated/route.php', 'getRoutes', true);
```

The `$routes` are gathered by the `Generator` by calling the `$getRoutes` callable. The structure calculated based on
these routes, by splitting a route up into sections. Each leaf has a key `"\0"` and a `Endpoint` object as value.

### Custom Invoker

The standard `Invoker` may be replaced by a class that implements `InvokableInterface`. This interface describes 2
methods; `generateInvocation()` and `generateDefault()`.

`generateInvocation()` generates the code of instantiating and calling an action for a given route. It takes
3 arguments;

* the matching route (as array)
* a callback for generating code that converts a method parameter to a path segment via path variables
* PHP code to instantiate class, where `%s` should be replaced with the classname

```php
$invoker->generateInvocation($route, function ($name, $type = null, $default = null) { /* ... */ }, '(new %s)'); 
```

`generateDefault()` doesn't take any arguments and should return code for if there is no matching route and no default
route has been generated. This method is not called when generating middleware.
