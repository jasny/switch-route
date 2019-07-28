<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Psr;

use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Test controller
 * @internal
 */
class InfoController
{
    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function defaultAction()
    {
        $response = ($this->responseFactory->createResponse())
            ->withHeader('Content-Type', 'text/plain');
        $response->getBody()->write("Some information");

        return $response;
    }
}
