<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\NotFound;
use Jasny\SwitchRoute\NotFoundMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
            ->willThrowException(new NotFound('no route'));

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
            ->willThrowException(new NotFound('no route'));

        $middleware = new NotFoundMiddleware($responseFactory);

        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }
}
