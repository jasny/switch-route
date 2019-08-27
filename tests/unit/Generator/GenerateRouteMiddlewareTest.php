<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\GenerateRouteMiddleware;
use Jasny\SwitchRoute\Tests\ExtendedClassesTrait;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\SwitchRoute\Generator\GenerateRouteMiddleware
 * @covers \Jasny\SwitchRoute\Generator\AbstractGenerate
 */
class GenerateRouteMiddlewareTest extends TestCase
{
    use RoutesTrait;
    use ExtendedClassesTrait;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->initExtendedGenerateRouteMiddleware();
    }

    public function testGenerateNs()
    {
        self::assertEquals(['', 'Dummy'], $this->extendedGenerateRouteMiddleware->callGenerateNs('Dummy'));
        /**
         * @todo self::assertEquals([...], $this->extendedGenerateFunction->callGenerateNs(SomeController::class));
         */
    }

    public function testGenerateDefault()
    {
        self::assertNotEmpty($this->extendedGenerateRouteMiddleware->callGenerateDefault(null));
    }

    public function testGenerateSwitch()
    {
        self::assertEquals('switch ($segments[0] ?? "\0") {' . PHP_EOL . '}', $this->extendedGenerateRouteMiddleware->callGenerateSwitch([]));
        /**
         * controller missed
         * @todo self::assertEquals('...', $this->extendedGenerateFunction->callGenerateSwitch($this->getStructure(), 1));
         */
    }

    public function testGenerateRoute()
    {
        /**
         * controller missed
         * @todo $route = $this->extendedGenerateFunction->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [])
         */
        self::assertSame(
            'return $request' . PHP_EOL .  '    ->withAttribute(\'route:controller\', \'Dummy\');',
            $this->extendedGenerateRouteMiddleware->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [])
        );
    }

    public function testGenerateEndpoint()
    {
        self::assertSame(
            '$request = $request->withAttribute(\'route:allowed_methods\', []);' . PHP_EOL . 'switch ($method) {' . PHP_EOL . '}',
            $this->extendedGenerateRouteMiddleware->callGenerateEndpoint(new Endpoint('/path'))
        );
    }
}
