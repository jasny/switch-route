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
        $allowedMethods = $request->getAttributes('route:allowed_attributes', []);

        if ($allowedMethods === []) {
            $response = $this->responseFactory->createResponse(404)
                ->withHeader('Content-Type', 'text/plain');
        } else {
            $response = $this->responseFactory->createResponse(405)
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Allow', join(', ', $allowedMethods));
        }

        $response->getBody()->write("Nothing here");
    }
}
