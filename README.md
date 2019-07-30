![jasny-banner](https://user-images.githubusercontent.com/100821/62123924-4c501c80-b2c9-11e9-9677-2ebc21d9b713.png)

Switch Route
===

[![Build Status](https://travis-ci.org/jasny/switch-route.svg?branch=master)](https://travis-ci.org/jasny/switch-route)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/switch-route/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/switch-route/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)
[![Packagist License](https://img.shields.io/packagist/l/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)

Fast routing for REST interfaces.

 

Installation
---

    composer require jasny/switch-route

Requires PHP 7.2.

Usage
---

In all examples we'll use the following function to get the routes;

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

Both `{id}` and `:id` syntax are supported for capturing url path variables. If the segment can be anything, but
doesn't need to be captured, use `*` (eg `/comments/{id}/*`).

Regular expressions on path variables (eg `{id:\d+}`) is **not** supported.

### Basic

By default the generator generates a function to route requests.

```php
use Jasny\SwitchRoute\Generator as RouteGenerator;
use Jasny\SwitchRoute\Invoker as RouteInvoker;

// Always generate in development env, but not in production.
$overwrite = (getenv('APPLIACTION_ENV') ?: 'dev') === 'dev';

$generator = new RouteGenerator();
$generator->generate('route', 'tmp/generated/route.php', 'getRoutes', $overwrite);
```

To route, include the generated file and call the `route` function.

```php
require 'tmp/generated/route.php';

$method = $_SERVER["REQUEST_METHOD"];
$path = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

route($method, $path);
```

### PSR-15 compatible middleware

It's recommended to use [PSR-7](https://www.php-fig.org/psr/psr-7/) as abstraction from the incoming request
parameters. `SwitchRoute\Generator` can generate a class that implements [PSR-15](https://www.php-fig.org/psr/psr-15/)
`MiddlewareInterface`. The `generateMiddleware` method returns the class name of the generated class.

This generated route middleware will set the attributes of the `ServerRequest` based on the matched route. The generated
invoke middleware will instantiate the controller and invoke the action.

This has been split into two steps, so you can add middleware that acts after the route is determined, but before it's
invoked.

```php
use App\Generated\RouteMiddleware;
use App\Generated\InvokeMiddleware
use HttpMessage\Factory as HttpFactory;
use HttpMessage\ServerRequest;
use Jasny\SwitchRoute\Generator as RouteGenerator;
use Jasny\SwitchRoute\Invoker as RouteInvoker;
use Relay\Relay;

$httpFactory = new HttpFactory();

$routeGenerator = new RouteGenerator(new Generator\GenerateRouteMiddleware());
$routeGenerator->generate(RouteMiddleware::class, 'tmp/generated/RouteMiddleware.php', 'getRoutes');

$invoker = new RouteInvoker();
$invokeGenerator = new RouteGenerator(new Generator\GenerateInvokeMiddleware($invoker));
$invokeGenerator->generate(InvokeMiddleware::class, 'tmp/generated/InvokeMiddleware.php', 'getRoutes');

$middleware[] = new RouteMiddleware();
$middleware[] = new InvokeMiddleware(fn($controllerClass) => new $controllerClass($httpFactory));

$relay = new Relay($middleware);

$request = new ServerRequest($_SERVER, $_COOKIE, $_QUERY, $_POST, $_FILES);
$response = $relay->handle($request);
```

_The middleware to create a controller is pretty naive. The class and method names should be properly camelcased and
your Controller or DI (dependency injection) library should provide for a proper way to create a controller._

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

### Pre-generated script

If the generated file already exists, the overhead of `SwitchRoute` is minimal. To have zero overhead in a production
environment, generate the classes or script in advance and use it directly.


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

```php

```

#### Generator::generate()

`Generator::generateScript()` will create a PHP script that will directly execute a route using the `$_SERVER`
superglobal.

This is the simpelest and fasted way of using `SwitchRoute`. However, it's recommended to use `[PSR-7]` with middleware
instead.

The script is written to the specified file, which should be included via `require`.

    Generator::generateScript(string $filename, Invoker $invoker, callable $getRoutes[, bool $force = true])

The optional `force` parameter defaults to `true`, meaning that a new script is generated each time that this method is
called. In a production environment, you should set this to `false` and delete the generated files each time you update
your application.

If `force` is `false` and the file already exists, no new file is generated. It's recommended to have the `opcache`
extension installed. This prevents additional checks on the file system for each http request.

#### Generator::generateMiddleware()

[PSR-7](https://www.php-fig.org/psr/psr-7/) is an abstraction for HTTP Requests. It allows you to more easily test your
application. It's avaible through [http_message extension from pecl](https://pecl.php.net/package/http_message) or one
of the many PSR-7 libraries.

The related [PSR-15](https://www.php-fig.org/psr/psr-15/) describes a way of processing `ServerRequest` objects through
handlers and middleware. This makes it easier to abstract your application.

`Generator::generateMiddleware()` generates a class that implements `Psr\Http\Server\MiddlewareInterface`.

The middleware will find the route and set the route properties as `ServerRequest` attributes.

    string Generator::generateMiddleware(string $filename, callable $getRoutes[, bool $force = true])

The method returns the class name of the generated invoke middleware.

If the optional `force` parameter is `true`, a new script is generated each time the method is called. If `force` is
`false` and the file already exists, no new file is generated. It's recommended to have the `opcache` extension
installed. This prevents additional checks on the file system for each http request.

The `Generator` will set a `route.methods_allowed` attribute if it matches the URL path, regardless of the HTTP
request method.

### Invoker

The `Invoker` is tasked with invoking the action or including the file as stated in the selected route. It doesn't do
this directly, but generates a script similar to the generator.

    new Invoker(callable $createInvokable[, ReflectionFactor $reflectionFactory]);

The controller class and action name are generated prior to instantiating the controller and invoking the action.

By default, the if the route has a `controller` property, the invoker will add 'Controller' and apply stud casing to
create the controller class name. The `action` property is taken as method, applying camel casing. It defaults to
`defaultAction`.

If only an `action` property is present, the invoker will add `Action` and apply stud casing to create the action class
name. This must be an [invokable object](https://www.php.net/manual/en/language.oop5.magic.php#object.invoke).

You can change how the invokable class and method names are generated by passing a callback to the constructor. The can
be used for instance to add a namespace to the controller and action class.

```php
$stud = fn($str) => strtr(ucwords($str, '-'), ['-' => '']);
$camel = fn($str) => strtr(lcfirst(ucwords($str, '-')), ['-' => '']);

$invoker = new RouteInvoker(function (?string $controller, ?string $action) {
    return $controller !== null
        ? ['App\\Generated\\' . $stud($controller) . 'Controller', $camel($action ?? 'default') . 'Action']
        : ['App\\Generated\\' . $stud($action) . 'Action', '__invoke'];
});
```

The invoker uses reflection to determine if the method is static or not. If the method isn't static the cont

Reflection is also used to find out the names of the arguments of the invokables. Those names are matched with the names
of URL path parameters (like `{id}` => `$id`) or `ServerRequest` attributes.

#### Invoker::generateMiddleWare()

`Invoker::generateMiddleWare()` generates a class that implements [PSR-15](https://www.php-fig.org/psr/psr-15/)
`Psr\Http\Server\MiddlewareInterface`.

    string Invoker::generateMiddleware(string $filename, callable $getRoutes[, bool $force = true])

The method returns the class name of the generated invoke middleware. That middleware optionally takes a callback
that's used to instantiate to controller, allowing for dependency injection. By default, the generated class will
simply do a `new`.

If the optional `force` parameter is `true`, a new script is generated each time the method is called. If `force` is
`false` and the file already exists, no new file is generated.
