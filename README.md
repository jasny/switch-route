![jasny-banner](https://user-images.githubusercontent.com/100821/61325728-9dfe9e80-a815-11e9-9e47-910fa7330d55.png)

Jasny Switch Route
===

[![Build Status](https://travis-ci.org/jasny/switch-route.svg?branch=master)](https://travis-ci.org/jasny/switch-route)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/switch-route/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/switch-route/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/switch-route/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)
[![Packagist License](https://img.shields.io/packagist/l/jasny/switch-route.svg)](https://packagist.org/packages/jasny/switch-route)

Generate a PHP route script. The script does `explode('/', $url)` get all segments and uses `switch` statements to get to the correct route.

Installation
---

    composer require jasny/switch-route

Usage
---

In all examples we'll use the following function to get the routes;

```php
function getRoutes(): array
{
    return [
        'GET    /'                 => ['controller' => 'info'],

        'GET    /users'            => ['controller' => 'users', 'action' => 'list'],
        'POST   /users'            => ['controller' => 'users', 'action' => 'add'],
        'GET    /users/:id'        => ['controller' => 'users', 'action' => 'get'],
        'POST   /users/:id'        => ['controller' => 'users', 'action' => 'update'],
        'DELETE /users/:id'        => ['controller' => 'users', 'action' => 'delete'],

        'GET    /users/:id/photos' => ['controller' => 'pictures', 'action' => 'list'],
        'POST   /users/:id/photos' => ['controller' => 'pictures', 'action' => 'add'],

        'POST   /export'           => ['include' => 'scripts/export.php'],
    ];
}
```

### Basic

```php
use Jasny\SwitchRoute\Generator as RouteGenerator;
use Jasny\SwitchRoute\Invoker as RouteInvoker;

$invoker = new RouteInvoker(
    fn($controller) => 'App\\' . uc_first($controller) . 'Controller',
    fn($action) => $action . 'Action',
);

$generator = new RouteGenerator();
$generator->generateScript('tmp/generated/route.php', 'getRoutes', $invoker);

require 'tmp/generated/route.php';
```

### Use route as PSR-15 compatible middleware

It's recommended to use [PSR-7](https://www.php-fig.org/psr/psr-7/) as abstraction from the incoming request
parameters. `SwitchRoute\Generator` can generate a class that implements [PSR-15](https://www.php-fig.org/psr/psr-15/)
`MiddlewareInterface`. The `generateMiddleware` method returns the class name of the generated class.

This generated route middleware will set the attributes of the `ServerRequest` based on the matched route. The
`NoRouteMiddleware` will respond with a `404 Not Found` or `405 Unsupported Method`. The generated invoke middleware
will instantiate the controller and invoke the action.

```php
use HttpMessage\Factory as HttpFactory;
use HttpMessage\ServerRequest;
use Jasny\SwitchRoute\Generator as RouteGenerator;
use Jasny\SwitchRoute\Invoker as RouteInvoker;
use Jasny\SwitchRoute\NoRouteMiddleware;
use Relay\Relay;

$httpFactory = new HttpFactory();

$routeGenerator = new RouteGenerator();
$route = $routeGenerator->generateMiddleware('tmp/generated/route.php', 'getRoutes');

$invokeGenerator = (new RouteInvoker(
    fn($controller) => 'App\\' . uc_first($name) . 'Controller',
    fn($action) => $action . 'Action'
));
$invoke = $invokeGenerator->generateMiddleWare('tmp/generated/route-invoke.php', 'getRoutes');

$middleware[] = new $route();
$middleware[] = new NoRouteMiddleware($httpFactory);
$middleware[] = new $invoke(fn($controllerClass) => new $controllerClass($httpFactory));

$relay = new Relay($middleware);

$request = new ServerRequest($_SERVER, $_COOKIE, $_QUERY, $_POST, $_FILES);
$response = $relay->handle($request);
```

_The middleware to create a controller is pretty naive. The class and method names should be properly camelcased and
your Controller or DI (dependency injection) library should provide for a proper way to create a controller._

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

#### Generator::generateScript()

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

    new Invoker(callable $genControllerClass, callable $genActionMethod[, ReflectionFactor $reflectionFactory]);

The controller class and action name are generated prior to instantiating the controller and invoking the action.

It uses reflection on the invoke callbacks to find out the names of the arguments. Those names are matched with the
names of URL path parameters (like `:id` => `$id`) or with `ServerRequest` attributes.

#### Invoker::generateMiddleWare()

`Invoker::generateMiddleWare()` generates a class that implements [PSR-15](https://www.php-fig.org/psr/psr-15/)
`Psr\Http\Server\MiddlewareInterface`.

    string Invoker::generateMiddleware(string $filename, callable $getRoutes[, bool $force = true])

The method returns the class name of the generated invoke middleware. That middleware optionally takes a callback
that's used to instantiate to controller, allowing for dependency injection. By default, the generated class will
simply do a `new`.

If the optional `force` parameter is `true`, a new script is generated each time the method is called. If `force` is
`false` and the file already exists, no new file is generated.

### NoRouteMiddleware

`NoRouteMiddleware` must be placed after the middleware that determines the routes and before the middleware that
invokes the routes. It returns a `404 Not Found` or `405 Unsupported Method` if no route was found for the url from
the `ServerRequest`. This is done by checking if the request either has a `controller` or `include` attribute.

    new NoRouteMiddleware(ResponseFactoryInterface $responseFactory)

The middleware creates a new response using the provided response factory. It sets the status to either `404` or `405`,
sets the `Content-Type` to `text/plain` and outputs a simple message. The status is based on `route.http_methods` set
by the Generator.

It sets the `Allow` response header for each request based on the`route.http_methods`. **This also done for requests
with a valid route.**

To disable the behavior and have this middleware always return a `404`, add a (callable) middleware function that
removed the attribute from the `ServerRequest`.


