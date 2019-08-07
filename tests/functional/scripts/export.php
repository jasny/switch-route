<?php

$response = ['data', 'export'];

// Please don't actually do something like this in production
if (isset($this) && $this instanceof \Psr\Http\Server\MiddlewareInterface) {
    $data = $response;

    $response = new \Nyholm\Psr7\Response(200, ['Content-Type' => 'application/json']);
    $response->getBody()->write(json_encode($data));
}

return $response;
