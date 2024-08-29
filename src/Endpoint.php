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
    protected string $path;
    protected array $routes = [];
    protected array $vars = [];


    /**
     * Endpoint constructor.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Add a route for this endpoint.
     */
    public function withRoute(string $method, mixed $route, array $vars): self
    {
        $method = strtoupper($method);

        if (isset($this->routes[$method])) {
            throw new InvalidRouteException("Duplicate route for '$method {$this->path}'");
        }

        $copy = clone $this;
        $copy->routes[$method] = $route;
        $copy->vars[$method] = $vars;

        return $copy;
    }

    /**
     * Get the path of this endpoint.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the allowed methods for this endpoint.
     */
    public function getAllowedMethods(): array
    {
        return array_diff(array_keys($this->routes), ['']);
    }

    /**
     * Get all routes for this endpoint.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get vars for a route.
     */
    public function getVars(string $method): array
    {
        $method = strtoupper($method);

        if (!isset($this->vars[$method])) {
            throw new OutOfBoundsException("Method '$method' not available for endpoint '{$this->path}'");
        }

        return $this->vars[$method];
    }


    /**
     * Get unique routes with methods and vars.
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
