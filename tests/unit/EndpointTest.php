<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Exception;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\TestHelper;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\SwitchRoute\Endpoint
 */
class EndpointTest extends TestCase
{
    use TestHelper;

    public function testGetPath()
    {
        $endpoint = new Endpoint('/users/*');

        $this->assertEquals('/users/*', $endpoint->getPath());
    }

    public function methodProvider()
    {
        return [
            ['GET', 'POST', 'PATCH'],
            ['get', 'post', 'patch'],
            ['Get', 'Post', 'Patch'],
        ];
    }

    /**
     * @dataProvider methodProvider
     */
    public function testWithRoute($method)
    {
        $endpoint = new Endpoint('/users/*');

        $newEndpoint = $endpoint->withRoute($method, ['action' => 'get-user'], ['id' => 1]);
        $this->assertEquals(['GET'], $newEndpoint->getAllowedMethods());

        $this->assertEquals(['id' => 1], $newEndpoint->getVars('GET'));
        $this->assertEquals(['GET' => ['action' => 'get-user']], $newEndpoint->getRoutes());

        // Test immutability
        $this->assertNotSame($endpoint, $newEndpoint);
        $this->assertEquals([], $endpoint->getRoutes());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testWithRouteWithDuplicateMethod($method)
    {
        $this->expectException(InvalidRouteException::class);
        $this->expectExceptionMessage("Duplicate route for 'GET /users/*'");

        (new Endpoint('/users/*'))
            ->withRoute('GET', ['action' => 'get-user'], [])
            ->withRoute('POST', ['action' => 'update-user'], [])
            ->withRoute($method, ['action' => 'fetch-user-by-id'], []);
    }

    public function testGetAllowedMethods()
    {
        $endpoint = (new Endpoint('/users/*'))
            ->withRoute('GET', [], [])
            ->withRoute('post', [], [])
            ->withRoute('Patch', [], [])
            ->withRoute('', [], []);

        $this->assertEquals(['GET', 'POST', 'PATCH'], $endpoint->getAllowedMethods());
    }

    public function testGetRoutes()
    {
        $endpoint = (new Endpoint('/users/*'))
            ->withRoute('GET', ['action' => 'get-user'], [])
            ->withRoute('post', ['action' => 'update-user'], [])
            ->withRoute('Patch', ['action' => 'patch-user'], []);

        $expected = [
            'GET' => ['action' => 'get-user'],
            'POST' => ['action' => 'update-user'],
            'PATCH' => ['action' => 'patch-user'],
        ];

        $this->assertEquals($expected, $endpoint->getRoutes());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testGetVars($get, $post, $patch)
    {
        $endpoint = (new Endpoint('/users/*/*'))
            ->withRoute('GET', [], ['foo' => 1])
            ->withRoute('post', [], ['bar' => 1, 'id' => 2])
            ->withRoute('Patch', [], ['qux' => 2]);

        $this->assertEquals(['foo' => 1], $endpoint->getVars($get));
        $this->assertEquals(['bar' => 1, 'id' => 2], $endpoint->getVars($post));
        $this->assertEquals(['qux' => 2], $endpoint->getVars($patch));
    }

    public function testGetVarsUnknownMethod()
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("Method 'DELETE' not available for endpoint '/users/*/*'");

        $endpoint = (new Endpoint('/users/*/*'))
            ->withRoute('GET', [], ['foo' => 1])
            ->withRoute('POST', [], ['bar' => 1, 'id' => 2])
            ->withRoute('PATCH', [], ['qux' => 2]);

        $endpoint->getVars('DELETE');
    }

    public function testGetUniqueRoutes()
    {
        $endpoint = (new Endpoint('/users/*'))
            ->withRoute('GET', ['action' => 'get-user'], [])
            ->withRoute('POST', ['action' => 'update-user'], ['id' => 1])
            ->withRoute('PUT', ['action' => 'update-user'], ['id' => 1])
            ->withRoute('PATCH', ['action' => 'update-user'], [])
            ->withRoute('UPDATE', ['action' => 'update-user'], ['id' => 1])
            ->withRoute('SAVE', ['action' => 'update-user'], []);

        $expected = [
            [['GET'], ['action' => 'get-user'], []],
            [['POST', 'PUT', 'UPDATE'], ['action' => 'update-user'], ['id' => 1]],
            [['PATCH', 'SAVE'], ['action' => 'update-user'], []],
        ];

        $routes = $endpoint->getUniqueRoutes();

        $this->assertInstanceOf(\Generator::class, $routes);
        $this->assertEquals($expected, iterator_to_array($routes, true));
    }
}
