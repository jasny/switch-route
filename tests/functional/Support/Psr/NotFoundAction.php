<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Psr;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test action
 * @internal
 */
class NotFoundAction
{
    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $allowedMethods = $request->getAttribute('route:allowed_methods', []);

        if ($allowedMethods === []) {
            $response = $this->responseFactory->createResponse(404, 'Not Found')
                ->withHeader('Content-Type', 'text/plain');
        } else {
            $response = $this->responseFactory->createResponse(405, 'Method Not Allowed')
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Allow', join(', ', $allowedMethods));
        }

        $response->getBody()->write("Nothing here");

        return $response;
    }
}
