<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateRouteMiddleware;
use Jasny\SwitchRoute\InvokerInterface;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\SwitchRoute\Generator\GenerateRouteMiddleware
 * @covers \Jasny\SwitchRoute\Generator\AbstractGenerate
 */
class GenerateRouteMiddlewareTest extends TestCase
{
    use RoutesTrait;

    public function test()
    {
        $routes = $this->getRoutes();
        $structure = $this->getStructure();

        $generate = new GenerateRouteMiddleware();

        $code = $generate('RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test.phps');
        $this->assertEquals($expected, $code);
    }

    public function testDefault()
    {
        $routes = ['GET /' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'info'], [])];

        $generate = new GenerateRouteMiddleware();

        $code = $generate('RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test-default.phps');
        $this->assertEquals($expected, $code);
    }

    public function testNs()
    {
        $routes = ['GET /' => ['controller' => 'info']];
        $structure = ["\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'info'], [])];

        $generate = new GenerateRouteMiddleware();

        $code = $generate('App\\Generated\\RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test-ns.phps');
        $this->assertEquals($expected, $code);
    }

    /**
     * extend class and check if ExtendedGenerateRouteMiddleware protected methods is available
     */
    public function testMethodsVisibility()
    {
        $extendedGenerateRouteMiddleware = new ExtendedGenerateRouteMiddleware();
        $extendedGenerateRouteMiddleware->testMethodsVisibility();
        self::assertTrue(true);
    }
}

class ExtendedGenerateRouteMiddleware extends GenerateRouteMiddleware
{
    public function testMethodsVisibility()
    {
        $this->generateDefault(null);
        $this->generateEndpoint(new Endpoint('/*'));
        $this->generateNs(Endpoint::class);
        $this->generateRoute('path', ['controller' => __NAMESPACE__ . '\Dummy'], []); // I can't write DummyController::class
        $this->generateSwitch([]);
        assert($this->invoker instanceof InvokerInterface);
    }
}
