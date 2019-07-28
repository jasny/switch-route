<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Psr;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test action
 * @internal
 */
class AddPhotosAction
{
    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(ServerRequestInterface $request, $id)
    {
        $response = ($this->responseFactory->createResponse())
            ->withHeader('Content-Type', 'text/plain');
        $response->getBody()->write("added photos for user $id");

        return $response;
    }
}
