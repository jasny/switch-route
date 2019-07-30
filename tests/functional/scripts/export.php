<?php

$response = ['data', 'export'];

// Please don't actually do something like this in production
if (isset($this) && isset($this->responseFactory)) {
    $data = $response;

    /** @var \Psr\Http\Message\ResponseInterface $response */
    $response = $this->responseFactory->createResponse()
        ->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode($data));
}

return $response;
