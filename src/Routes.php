<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Spatie\Regex\Regex;

/**
 * Collection of routes.
 * Accessible as associative array.
 */
class Routes implements ArrayAccess, IteratorAggregate
{
    protected string $prefix = '';

    /** @var array<string,array> */
    protected array $routes = [];

    /** @var Endpoint[] */
    protected array $structure = [];

    /**
     * Class constructor
     */
    public function __construct(iterable $routes = [], string $prefix = '')
    {
        $this->prefix = $prefix;
        $this->add($routes);
    }

    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string|string[] $method
     * @param mixed           $path
     * @param array           $vars
     */
    public function addRoute($method, string $path, array $vars): void
    {
        $key = join('|', (array)$method) . ' ' . urlencode($this->prefix . $path);
        $this->routes[$key] = $vars;
    }

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the callback will have the given group prefix prepended.
     */
    public function addGroup(string $prefix, callable $callback): void
    {
        $subRoutes = new self([], $prefix);
        $callback($subRoutes);

        $this->add($subRoutes);
    }

    /**
     * Adds a fallback route to the collection
     *
     * This is simply an alias of $this->addRoute('*', $route, $handler)
     *
     * @param mixed $handler
     */
    public function any(string $route, $handler): void
    {
        $this->addRoute('*', $route, $handler);
    }

    /**
     * Adds a GET route to the collection
     *
     * This is simply an alias of $this->addRoute('GET', $route, $handler)
     *
     * @param mixed $handler
     */
    public function get(string $route, $handler): void
    {
        $this->addRoute('GET', $route, $handler);
    }

    /**
     * Adds a POST route to the collection
     *
     * This is simply an alias of $this->addRoute('POST', $route, $handler)
     *
     * @param mixed $handler
     */
    public function post(string $route, $handler): void
    {
        $this->addRoute('POST', $route, $handler);
    }

    /**
     * Adds a PUT route to the collection
     *
     * This is simply an alias of $this->addRoute('PUT', $route, $handler)
     *
     * @param mixed $handler
     */
    public function put(string $route, $handler): void
    {
        $this->addRoute('PUT', $route, $handler);
    }

    /**
     * Adds a DELETE route to the collection
     *
     * This is simply an alias of $this->addRoute('DELETE', $route, $handler)
     *
     * @param mixed $handler
     */
    public function delete(string $route, $handler): void
    {
        $this->addRoute('DELETE', $route, $handler);
    }

    /**
     * Adds a PATCH route to the collection
     *
     * This is simply an alias of $this->addRoute('PATCH', $route, $handler)
     *
     * @param mixed $handler
     */
    public function patch(string $route, $handler): void
    {
        $this->addRoute('PATCH', $route, $handler);
    }

    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->addRoute('HEAD', $route, $handler)
     *
     * @param mixed $handler
     */
    public function head(string $route, $handler): void
    {
        $this->addRoute('HEAD', $route, $handler);
    }

    /**
     * Adds an OPTIONS route to the collection
     *
     * This is simply an alias of $this->addRoute('OPTIONS', $route, $handler)
     *
     * @param mixed $handler
     */
    public function options(string $route, $handler): void
    {
        $this->addRoute('OPTIONS', $route, $handler);
    }


    /**
     * Create a structure with a leaf for each endpoint.
     *
     * @param iterable $routes
     * @throws InvalidRoute
     */
    public function add(iterable $routes): void
    {
        foreach ($routes as $key => $vars) {
            $this[$key] = $vars;
        }
    }

    /**
     * Return routes in a structured way for generating the switch statement.
     *
     * @return array<string,mixed>
     */
    public function structure(): array
    {
        $structure = [];

        foreach ($this->routes as $key => $route) {
            if ($key === 'default') {
                $structure["\e"] = (new Endpoint(''))->withRoute('', $route, []);
                continue;
            }

            $match = Regex::match('~^\s*(?P<methods>\w+(?:\|\w+)*)\s+(?P<path>/\S*)\s*$~', $key);

            if (!is_string($key) || !$match->hasMatch()) {
                throw new InvalidRoute("Invalid routing key '$key': should be 'METHOD /path'");
            }

            $methods = $match->namedGroup('methods');
            [$segments, $vars] = $this->splitPath($match->namedGroup('path'));

            $pointer =& $structure;
            foreach ($segments as $segment) {
                $pointer[$segment] = $pointer[$segment] ?? [];
                $pointer =& $pointer[$segment];
            }

            if (!isset($pointer["\0"])) {
                $pointer["\0"] = new Endpoint('/' . join('/', $segments));
            }

            foreach (explode('|', $methods) as $method) {
                $pointer["\0"] = $pointer["\0"]->withRoute($method, $route, $vars);
            }
        }

        return $structure;
    }

    /**
     * Split path into segments and extract variables.
     *
     * @param string $path
     * @return array[]
     */
    protected function splitPath(string $path): array
    {
        if ($path === '/') {
            return [[], []];
        }

        $segments = explode('/', substr($path, 1));
        $vars = [];

        foreach ($segments as $index => &$segment) {
            $match = Regex::match('/^(?|:(?P<var>\w+)|\{(?P<var>\w+)\})$/', $segment);

            if ($match->hasMatch()) {
                $vars[$match->namedGroup('var')] = $index;
                $segment = '*';
            }
        }

        return [$segments, $vars];
    }


    /**
     * Normalize the route key as `method /path`
     */
    protected function normalizeKey(string $key): string
    {
        return Regex::replace('/\s{2,}/', ' ', $key)->result();
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        return new ArrayIterator($this->routes);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($key)
    {
        return isset($this->routes[$this->normalizeKey($key)]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($key)
    {
        return $this->routes[$this->normalizeKey($key)];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($key, $value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Route vars should be a variable");
        }

        $this->routes[$this->normalizeKey($key)] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($key)
    {
        unset($this->routes[$this->normalizeKey($key)]);
    }
}
