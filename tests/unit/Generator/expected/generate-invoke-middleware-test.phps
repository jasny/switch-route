<?php

declare(strict_types=1);

use Jasny\SwitchRoute\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * PSR-15 compatible middleware that invokes an action based on the route attributes of the server request.
 *
 * This file is generated by SwitchRoute.
 * Do not modify it manually. Any changes will be overwritten.
 */
class InvokeMiddleware implements MiddlewareInterface
{
    /**
     * @var callable|null
     */
    protected $instantiate;

    /**
     * @param callable $instantiate  Instantiate controller / action classes.
     */
    public function __construct(?callable $instantiate = null)
    {
        $this->instantiate = $instantiate;
    }

    /**
     * Process an incoming server request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $include = $request->getAttribute('route:include', null);
        if ($include !== null) {
            return require $include;
        }

        $controller = $request->getAttribute('route:controller', '');
        $action = $request->getAttribute('route:action', '');

        switch ($controller) {
            case 'InfoController':
                switch ($action) {
                    case '':
                        return call('InfoController', '', $request, $request->getAttribute('route:{id}', NULL));
                }
                break;
            case 'UserController':
                switch ($action) {
                    case 'listAction':
                        return call('UserController', 'listAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'addAction':
                        return call('UserController', 'addAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'getAction':
                        return call('UserController', 'getAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'updateAction':
                        return call('UserController', 'updateAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'deleteAction':
                        return call('UserController', 'deleteAction', $request, $request->getAttribute('route:{id}', NULL));
                }
                break;
            case '':
                switch ($action) {
                    case 'NotFoundAction':
                        return call('', 'NotFoundAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'ListPhotosAction':
                        return call('', 'ListPhotosAction', $request, $request->getAttribute('route:{id}', NULL));
                    case 'AddPhotosAction':
                        return call('', 'AddPhotosAction', $request, $request->getAttribute('route:{id}', NULL));
                }
                break;
        }

        throw new NotFoundException("No default route specified");
    }
}