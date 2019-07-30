<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use OutOfBoundsException;

/**
 * Endpoint for routing.
 * @internal
 */
final class Endpoint
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var array[]
     */
    protected $routes = [];

    /**
     * @var array[]
     */
    protected $vars = [];


    /**
     * Endpoint constructor.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Add a route for this endpoint.
     *
     * @param string $method
     * @param mixed  $route
     * @param array  $vars
     * @return static
     */
    public function withRoute(string $method, $route, array $vars): self
    {
        if (isset($this->routes[$method])) {
            throw new InvalidRouteException("Duplicate route for '$method {$this->path}'");
        }

        $copy = clone $this;
        $copy->routes[strtoupper($method)] = $route;
        $copy->vars[strtoupper($method)] = $vars;

        return $copy;
    }

    /**
     * Get the path of this endpoint.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the allowed methods for this endpoint.
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return array_diff(array_keys($this->routes), ['']);
    }

    /**
     * Get all routes for this endpoint.
     *
     * @return array[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get vars for a route.
     *
     * @param string $method
     * @return array[]
     */
    public function getVars(string $method): array
    {
        if (!isset($this->vars[$method])) {
            throw new OutOfBoundsException("Method '$method' not available for endpoint '{$this->path}'");
        }

        return $this->vars[$method];
    }


    /**
     * Get unique routes with methods and vars.
     *
     * @param array $routes
     * @return \Generator
     */
    public function getUniqueRoutes(): \Generator
    {
        $queue = array_keys($this->routes);

        while ($queue !== []) {
            $method = reset($queue);

            $route = $this->routes[$method];
            $vars = $this->vars[$method];

            $methods = array_values(array_intersect(
                array_keys($this->routes, $route, true),
                array_keys($this->vars, $vars, true)
            ));

            yield [$methods, $route, $vars];

            $queue = array_diff($queue, $methods);
        }
    }
}
