<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\NotFoundException;
use Jasny\SwitchRoute\NotFoundMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Relay\RequestHandler;

/**
 * @covers \Jasny\SwitchRoute\NotFoundMiddleware
 */
class NotFoundMiddlewareTest extends TestCase
{
    public function testFound()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->never())->method($this->anything());

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($response);

        $middleware = new NotFoundMiddleware($responseFactory);

        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testNotFound()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getAttribute')
            ->with('route:allowed_methods')
            ->willReturnArgument(1);

        $responseBody = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('withHeader')
            ->with('Content-Type', 'text/plain')
            ->willReturnSelf();
        $response->expects($this->once())->method('getBody')->willReturn($responseBody);
        $responseBody->expects($this->once())->method('write')->with('Not Found');

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->once())->method('createResponse')
            ->with(404, 'Not Found')
            ->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->identicalTo($request))
            ->willThrowException(new NotFoundException('no route'));

        $middleware = new NotFoundMiddleware($responseFactory);

        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testMethodNotAllowed()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getAttribute')
            ->with('route:allowed_methods')
            ->willReturn(['GET', 'POST']);

        $responseBody = $this->createMock(StreamInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('withHeader')
            ->withConsecutive(
                ['Content-Type', 'text/plain'],
                ['Allow', 'GET, POST']
            )
            ->willReturnSelf();
        $response->expects($this->once())->method('getBody')->willReturn($responseBody);
        $responseBody->expects($this->once())->method('write')->with('Method Not Allowed');

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->once())->method('createResponse')
            ->with(405, 'Method Not Allowed')
            ->willReturn($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->identicalTo($request))
            ->willThrowException(new NotFoundException('no route'));

        $middleware = new NotFoundMiddleware($responseFactory);

        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    /**
     * extend class and check if ExtendedNotFoundMiddleware protected methods is available
     */
    public function testMethodsVisibility()
    {
        $extendedNotFoundMiddleware = new ExtendedNotFoundMiddleware(new TestResponseFactory());
        $extendedNotFoundMiddleware->testMethodsVisibility();
        self::assertTrue(true);
    }
}

class ExtendedNotFoundMiddleware extends NotFoundMiddleware
{
    public function testMethodsVisibility()
    {
        $this->notFound(new ServerRequest('POST', 'http://localhost'));
        $this->process(new ServerRequest('POST', 'http://localhost'), new TestRequestHandler([]));
    }
}

class TestRequestHandler extends RequestHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response();
    }
}

class TestResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response();
    }
}
