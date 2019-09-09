<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\NotFoundMiddleware;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory as HttpFactory;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Relay\Relay;

/**
 * @coversNothing
 */
class MiddlewareTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected static $root;

    /**
     * @var string
     */
    protected static $namespace;

    /**
     * @var string
     */
    protected static $file;

    /**
     * @var MiddlewareInterface
     */
    protected $routeMiddleware;

    /**
     * @var NotFoundMiddleware
     */
    protected $notFoundMiddleware;

    /**
     * @var MiddlewareInterface
     */
    protected $invokeMiddleware;


    public static function setUpBeforeClass(): void
    {
        self::$root = vfsStream::setup('tmp');
        self::$namespace = 'Temp' . bin2hex(random_bytes(8));

        self::createRouteMiddleware();
        self::createInvokeMiddleware();
    }

    protected static function createRouteMiddleware(): void
    {
        $file = '/tmp/generated/RouteMiddleware.php';//vfsStream::url('tmp/generated/RouteMiddleware.php');

        $generator = new Generator(new Generator\GenerateRouteMiddleware());
        $generator->generate(self::$namespace . '\\RouteMiddleware', $file, [__CLASS__, 'getRoutes'], true);

        require_once $file;
    }

    protected static function createInvokeMiddleware(): void
    {
        $file = '/tmp/generated/InvokeMiddleware.php';//vfsStream::url('tmp/generated/InvokeMiddleware.php');

        $invoker = new Invoker(function ($controller, $action) {
            [$class, $method] = $controller !== null
                ? [$controller . 'Controller', ($action ?? 'default') . 'Action']
                : [$action . 'Action', '__invoke'];

            return [
                __NAMESPACE__ . '\\Support\\Psr\\' . strtr(ucwords($class, '-'), ['-' => '']),
                strtr(lcfirst(ucwords($method, '-')), ['-' => ''])
            ];
        });

        $generator = new Generator(new Generator\GenerateInvokeMiddleware($invoker));
        $generator->generate(self::$namespace . '\\InvokeMiddleware', $file, [__CLASS__, 'getRoutes'], true);

        require_once $file;
    }

    public static function tearDownAfterClass(): void
    {
        self::$root = null;
    }

    public function setUp(): void
    {
        $factory = new HttpFactory();

        $routeMiddlewareClass = self::$namespace . '\\RouteMiddleware';
        $this->routeMiddleware = new $routeMiddlewareClass();

        $this->notFoundMiddleware = new NotFoundMiddleware($factory);

        $instantiate = function (string $class) use ($factory) {
            return new $class($factory);
        };

        $invokeMiddlewareClass = self::$namespace . '\\InvokeMiddleware';
        $this->invokeMiddleware = new $invokeMiddlewareClass($instantiate);
    }

    /**
     * @dataProvider provider
     */
    public function test(string $method, string $path, $expected, $expectedStatus = 200)
    {
        $relay = new Relay([$this->routeMiddleware, $this->notFoundMiddleware, $this->invokeMiddleware]);

        /** @var ServerRequestInterface $request */
        $request = new ServerRequest($method, "https://example.com{$path}");

        $response = $relay->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->assertEquals($expectedStatus, $response->getStatusCode());

        if ($response->getStatusCode() != 200) {
            $result = $response->getStatusCode() . ' ' . $response->getReasonPhrase()
                . ($response->hasHeader('Allow') ? ' (' . $response->getHeaderLine('Allow') . ')' : '');
            $this->assertEquals($expected, $result);
            $this->assertEquals('Nothing here', (string)$response->getBody());
            $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));

            return;
        }

        if (is_string($expected)) {
            $this->assertEquals($expected, (string)$response->getBody());
            $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        } else {
            $this->assertEquals(json_encode($expected), (string)$response->getBody());
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        }

        $this->assertEquals(200, $response->getStatusCode());
    }
}
