<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Psr;

use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Test action
 * @internal
 */
class ListPhotosAction
{
    protected $photos = [
        1 => [
            ['id' => 11, 'name' => 'sunrise'],
            ['id' => 12, 'name' => 'sunset'],
        ],
        2 => [
            ['id' => 13, 'name' => 'red'],
            ['id' => 14, 'name' => 'green'],
            ['id' => 15, 'name' => 'blue'],
        ]
    ];

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function __invoke($id)
    {
        $response = ($this->responseFactory->createResponse())
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($this->photos[$id]));

        return $response;
    }
}
