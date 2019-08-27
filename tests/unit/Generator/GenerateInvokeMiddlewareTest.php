<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Closure;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\InvokerInterface;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;

/**
 * @covers \Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware
 * @covers \Jasny\SwitchRoute\Generator\AbstractGenerate
 */
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

    public function test()
    {
        $routes = $this->getRoutes();
        $routeArgs = $this->getRouteArgs();
        $structure = $this->getStructure();

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->exactly(count($routeArgs)))->method('generateInvocation')
            ->withConsecutive(...$routeArgs)
            ->willReturnCallback(function ($route, callable $genArg) {
                ['controller' => $controller, 'action' => $action] = $route;
                $request = $genArg('request', ServerRequestInterface::class, null);
                $id = $genArg('id', '', null);
                return sprintf("call('%s', '%s', %s, %s)", $controller, $action, $request, $id);
            });

        $generate = new GenerateInvokeMiddleware($invoker);

        $code = $generate('InvokeMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-invoke-middleware-test.phps');
        $this->assertEquals($expected, $code);
    }

    public function testWithInvalidRoute()
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

    public function testWithReflectionException()
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Invalid route for 'GET /*'. Can't call info()");

        $routes = ['GET /{id}' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/*'))->withRoute('GET', ['controller' => 'info'], [])];

        $invoker = $this->createMock(Invoker::class);
        $invoker->expects($this->once())->method('generateInvocation')
            ->willThrowException(new ReflectionException("Can't call info()"));

        $generate = new GenerateInvokeMiddleware($invoker);

        $generate('', $routes, $structure);
    }

    /**
     * extend class and check if ExtendedGenerateInvokeMiddleware protected methods is available
     */
    public function testMethodsVisibility()
    {
        $extendedGenerateInvokeMiddleware = new ExtendedGenerateInvokeMiddleware();
        $extendedGenerateInvokeMiddleware->testMethodsVisibility();
        self::assertTrue(true);
    }
}

class ExtendedGenerateInvokeMiddleware extends GenerateInvokeMiddleware
{
    public function testMethodsVisibility()
    {
        $this->groupRoutes([]);
        $this->generateSwitchFromRoutes([]);
        $this->generateEndpoint(new Endpoint('/*'));
        $this->generateNs(Endpoint::class);
        $this->generateRoute('path', ['controller' => __NAMESPACE__ . '\Dummy'], []); // I can't write DummyController::class
        $this->generateSwitch([]);
        assert($this->invoker instanceof InvokerInterface);
    }
}
