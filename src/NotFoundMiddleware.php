<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that gives a 404 or 405 response on a NotFoundException.
 */
class NotFoundMiddleware implements MiddlewareInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * Class constructor.
     *
     * @param ResponseFactoryInterface $responseFactory Used for default not-found response.
     */
    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * The default action for when no route matches.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function notFound(ServerRequestInterface $request): ResponseInterface
    {
        $allowedMethods = $request->getAttribute('route:allowed_methods', []);

        if ($allowedMethods === []) {
            $response = $this->responseFactory->createResponse(404, 'Not Found')
                ->withHeader('Content-Type', 'text/plain');
            $response->getBody()->write('Not Found');
        } else {
            $response = $this->responseFactory->createResponse(405, 'Method Not Allowed')
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Allow', join(', ', $allowedMethods));
            $response->getBody()->write('Method Not Allowed');
        }

        return $response;
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (NotFoundException $exception) {
            return $this->notFound($request);
        }
    }
}
