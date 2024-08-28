<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator\AbstractGenerate;
use Jasny\SwitchRoute\Generator\GenerateRouteMiddleware;
use Jasny\SwitchRoute\Tests\RoutesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerateRouteMiddleware::class)]
#[CoversClass(AbstractGenerate::class)]
class GenerateRouteMiddlewareTest extends TestCase
{
    use RoutesTrait;

    public function testGenerate()
    {
        $routes = $this->getRoutes();
        $structure = $this->getStructure();

        $generate = new GenerateRouteMiddleware();

        $code = $generate('RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test.phps');
        $this->assertEquals($expected, $code);
    }

    public function testGenerateDefaultRoute()
    {
        $routes = ['GET /' => ['controller' => 'InfoController']];
        $structure = ["\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'InfoController'], [])];

        $generate = new GenerateRouteMiddleware();

        $code = $generate('RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test-default.phps');
        $this->assertEquals($expected, $code);
    }

    public function testGenerateWithNamespace()
    {
        $routes = ['GET /' => ['controller' => 'InfoController']];
        $structure = ["\0" => (new Endpoint('/'))->withRoute('GET', ['controller' => 'InfoController'], [])];

        $generate = new GenerateRouteMiddleware();

        $code = $generate('App\\Generated\\RouteMiddleware', $routes, $structure);

        $expected = file_get_contents(__DIR__ . '/expected/generate-route-middleware-test-ns.phps');
        $this->assertEquals($expected, $code);
    }
}
