<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use HttpMessage\ServerRequest;
use HttpMessage\Response;
use HttpMessage\Uri;
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseFactoryInterface;
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
        $file = '/tmp/generated/RouteMiddleware.php'; //vfsStream::url('tmp/generated/RouteMiddleware.php');

        $generator = new Generator(new Generator\GenerateRouteMiddleware());
        $generator->generate(self::$namespace . '\\RouteMiddleware', $file, [__CLASS__, 'getRoutes'], true);

        require_once $file;
    }

    protected static function createInvokeMiddleware(): void
    {
        $file = '/tmp/generated/InvokeMiddleware.php'; //vfsStream::url('tmp/generated/InvokeMiddleware.php');

        $invoker = new Invoker(function ($class, $action) {
            [$class, $method] = Invoker::createInvokable($class, $action);
            return [__NAMESPACE__ . '\\Support\\Psr\\' . $class, $method];
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
        $routeMiddlewareClass = self::$namespace . '\\RouteMiddleware';
        $this->routeMiddleware = new $routeMiddlewareClass();

        $responseFactory = new class () implements ResponseFactoryInterface {
            public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
            {
                return (new Response)->withStatus($code, $reasonPhrase);
            }
        };
        $instantiate = function (string $class) use ($responseFactory) {
            return new $class($responseFactory);
        };

        $invokeMiddlewareClass = self::$namespace . '\\InvokeMiddleware';
        $this->invokeMiddleware = new $invokeMiddlewareClass($responseFactory, $instantiate);
    }


    public function provider()
    {
        return [
            ['GET', '/', "Some information"],

            ['GET', '/users', array_values($this->users)],
            ['POST', '/users', "added user"],
            ['GET', '/users/1', $this->users[1]],
            ['GET', '/users/2', $this->users[2]],
            ['POST', '/users/1', "updated user '1'"],
            ['PUT', '/users/1', "updated user '1'"],
            ['DELETE', '/users/2', "deleted user '2'"],

            ['GET', '/users/1/photos', $this->photos[1]],
            ['GET', '/users/2/photos', $this->photos[2]],
            ['POST', '/users/1/photos', "added photos for user 1"],

            ['POST', '/export', ['data', 'export']],

            ['POST', '/foo', "Nothing here", 404],
            ['DELETE', '/users', "Nothing here", 405, "GET, POST"],
            ['PATCH', '/users/1', "Nothing here", 405, "GET, POST, PUT, DELETE"],

            // Test with trailing slash
            ['GET', '/users/', array_values($this->users)],
            ['GET', '/users/2/', $this->users[2]],
            ['GET', '/users/2/photos/', $this->photos[2]],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test(string $method, string $path, $expected, $expectedStatus = 200, $expectedAccept = null)
    {
        $relay = new Relay([$this->routeMiddleware, $this->invokeMiddleware]);

        /** @var ServerRequestInterface $request */
        $request = (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri("https://example.com{$path}"));

        $response = $relay->handle($request);

        if (is_string($expected)) {
            $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
            $this->assertEquals($expected, (string)$response->getBody());
        } else {
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
            $this->assertEquals(json_encode($expected), (string)$response->getBody());
        }

        $this->assertEquals($expectedStatus, $response->getStatusCode());

        if ($expectedAccept !== null) {
            $this->assertEquals($expectedAccept, $response->getHeaderLine('Accept'));
        }
    }
}
