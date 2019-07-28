<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Psr;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test controller
 * @internal
 */
class UserController
{
    /**
     * @var array
     */
    protected $users = [
        1 => ['id' => 1, 'name' => 'joe', 'email' => 'joe@example.com'],
        2 => ['id' => 2, 'name' => 'jane', 'email' => 'jane@example.com'],
    ];

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    protected function textResponse(string $text)
    {
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write($text);

        return $response;
    }

    protected function jsonResponse($data)
    {
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data));

        return $response;
    }


    public function listAction(): ResponseInterface
    {
        return $this->jsonResponse(array_values($this->users));
    }

    public function addAction(): ResponseInterface
    {
        return $this->textResponse("added user");
    }

    public function getAction($id): ResponseInterface
    {
        return $this->jsonResponse($this->users[(int)$id]);
    }

    public function updateAction($id): ResponseInterface
    {
        return $this->textResponse("updated user '$id'");
    }

    public function deleteAction($id): ResponseInterface
    {
        return $this->textResponse("deleted user '$id'");
    }
}
