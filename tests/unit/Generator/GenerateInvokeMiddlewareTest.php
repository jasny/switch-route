<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Closure;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\InvokerInterface;
use Jasny\SwitchRoute\Tests\ExtendedClassesTrait;
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
    use ExtendedClassesTrait;

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

    /** test GenerateInvokeMiddleware protected members */

    protected function setUp(): void
    {
        parent::setUp();
        $this->initExtendedGenerateInvokeMiddleware();
    }

    public function testGenerateNs()
    {
        self::assertEquals(['', 'Dummy'], $this->extendedGenerateInvokeMiddleware->callGenerateNs('Dummy'));
        /**
         * @todo self::assertEquals([...], $this->extendedGenerateInvokeMiddleware->callGenerateNs(SomeController::class));
         */
    }

    public function testGenerateSwitch()
    {
        self::assertEquals('switch ($segments[0] ?? "\0") {' . PHP_EOL . '}', $this->extendedGenerateInvokeMiddleware->callGenerateSwitch([]));
        /**
         * controller missed
         * @todo self::assertEquals('...', $this->extendedGenerateInvokeMiddleware->callGenerateSwitch($this->getStructure(), 1));
         */
    }

    public function testGenerateRoute()
    {
        /**
         * controller missed
         * @todo $route = $this->extendedGenerateInvokeMiddleware->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [])
         */
        try {
            $this->extendedGenerateInvokeMiddleware->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [], null);
        } catch (InvalidRouteException $exception) {
            self::assertTrue(true);
        } catch (\Exception $exception) {
            self::assertTrue(false, $exception->getMessage());
        }
    }

    public function testGenerateEndpoint()
    {
        self::assertSame(
            'switch ($method) {' . PHP_EOL . '}',
            $this->extendedGenerateInvokeMiddleware->callGenerateEndpoint(new Endpoint('/path'))
        );
    }

    public function testGroupRoutes()
    {
        self::assertSame(
            [],
            $this->extendedGenerateInvokeMiddleware->callGroupRoutes([])
        );
        /**
         * @todo $this->extendedGenerateInvokeMiddleware->callGroupRoutes($this->getRoutes())
         */
    }

    public function testGenerateSwitchFromRoutes()
    {
        self::assertNotEmpty($this->extendedGenerateInvokeMiddleware->callGenerateSwitchFromRoutes([]));
        /**
         * @todo $this->extendedGenerateInvokeMiddleware->callGenerateSwitchFromRoutes($this->getRoutes())
         */
    }

    public function testInvoker()
    {
        self::assertInstanceOf(InvokerInterface::class, $this->extendedGenerateInvokeMiddleware->getInvoker());
    }
}
