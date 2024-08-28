<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Closure;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use Jasny\SwitchRoute\Tests\Utils\Consecutive;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;

#[CoversClass(\Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware::class)]
#[CoversClass(\Jasny\SwitchRoute\Generator\AbstractGenerate::class)]
class GenerateInvokeMiddlewareTest extends TestCase
{
    use RoutesTrait;

    protected function getRouteArgs()
    {
        $routes = $this->getRoutes();

        $routeArgs = [];
        $isClosure = $this->isInstanceOf(Closure::class);

        foreach ($routes as $key => $route) {
            if (isset($route['include'])) {
                continue;
            }

            $routeArgs[] = [$route + ['controller' => null, 'action' => null], $isClosure];
        }

        return $routeArgs;
    }

    public static function serverRequestProvider()
    {
        return [
            ServerRequestInterface::class => [ServerRequestInterface::class],
            ServerRequest::class => [ServerRequest::class],
        ];
    }

    #[DataProvider('serverRequestProvider')]
    public function testGenerate(string $serverRequestClass)
    {
        $routes = $this->getRoutes();
        $routeArgs = $this->getRouteArgs();
        $structure = $this->getStructure();

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->exactly(count($routeArgs)))->method('generateInvocation')
            ->with(...Consecutive::create(...$routeArgs))
            ->willReturnCallback(function ($route, callable $genArg) use ($serverRequestClass) {
                ['controller' => $controller, 'action' => $action] = $route;
                $request = $genArg('request', $serverRequestClass, null);
                $id = $genArg('id', '', null);
                return sprintf("call('%s', '%s', %s, %s)", $controller, $action, $request, $id);
            });

        $generate = new GenerateInvokeMiddleware($invoker);

        $code = $generate('InvokeMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-invoke-middleware-test.phps');
        $this->assertEquals($expected, $code);
    }

    public function testInvalidRoute()
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Route for 'GET /*' should specify 'include', 'controller', or 'action'");

        $routes = ['GET /{id}' => ['foo' => 'bar']];
        $structure = ["\0" => (new Endpoint('/*'))->withRoute('GET', ['foo' => 'bar'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->never())->method('generateInvocation');

        $generate = new GenerateInvokeMiddleware($invoker);

        $generate('', $routes, $structure);
    }

    public function testReflectionException()
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Invalid route for 'GET /*'. Can't call info()");
        $this->expectExceptionCode(0);

        $routes = ['GET /{id}' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/*'))->withRoute('GET', ['controller' => 'info'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->once())->method('generateInvocation')
            ->willThrowException(new ReflectionException("Can't call info()"));

        $generate = new GenerateInvokeMiddleware($invoker);

        $generate('', $routes, $structure);
    }
}
