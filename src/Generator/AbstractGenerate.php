<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\InvalidRouteException;

/**
 * Base class for switch script generation.
 */
abstract class AbstractGenerate
{
    /**
     * Generate routing code for an endpoint.
     *
     * @param string $key
     * @param array  $route
     * @param array  $vars
     * @return string
     * @throws InvalidRouteException
     */
    abstract protected function generateRoute(string $key, array $route, array $vars): string;

    /**
     * Generate the PHP script with a switch for routing.
     *
     * @param array $structure
     * @param int   $level      Structure depth
     * @return string
     */
    protected function generateSwitch(array $structure, int $level = 0): string
    {
        $indent = str_repeat(' ', $level * 8);
        $code[] = $indent . sprintf("switch (\$segments[{$level}] ?? %s) {", '"\0"');

        foreach ($structure as $segment => $sub) {
            $code[] = $indent . '    ' . ($segment === '*' ? 'default' : 'case "' . addslashes($segment) . '"') . ':';

            if ($sub instanceof Endpoint) {
                $code[] = self::indent($this->generateEndpoint($sub), ($level + 1) * 8);
            } else {
                $code[] = $this->generateSwitch($sub, $level + 1); // recursion
            }
            $code[] = $indent . "        break " . ($level + 1) . ";";
        }

        $code[] = $indent . "}";

        return join("\n", $code);
    }

    /**
     * Generate a statement for an endpoint.
     *
     * @param Endpoint $endpoint
     * @return string
     */
    protected function generateEndpoint(Endpoint $endpoint): string
    {
        $routes = $this->uniqueRoutes($endpoint->getRoutes());

        $code[] = "switch (\$method) {";

        foreach ($routes as $methods => $route) {
            foreach ($methods as $method) {
                $code[] = sprintf("    case '%s':", $method);
            }

            $key = join('|', $methods) . ' ' . $endpoint->getPath();
            $routeCode = $this->generateRoute($key, $route, $endpoint->getVars($method));
            $code[] = AbstractGenerate::indent($routeCode, 8);
        }

        $code[] = "}";

        return join("\n", $code);
    }

    /**
     * Get unique routes with methods.
     * Yields pairs of methods (array) and route.
     *
     * @param array $routes
     * @return \Generator
     */
    protected function uniqueRoutes(array $routes): \Generator
    {
        $done = [];

        foreach ($routes as $route) {
            if (in_array($route, $done, true)) {
                continue;
            }
            $done[] = $route;

            $methods = array_keys($routes, $route, true);
            yield $methods => $route;
        }
    }

    /**
     * Generate namespace code and extract class name from fqcn.
     *
     * @param string $class
     * @return array
     */
    protected function generateNs(string $class): array
    {
        $parts = explode('\\', $class);
        $className = array_pop($parts);
        $namespace = $parts !== [] ? "\n" . 'namespace ' . join('\\', $parts) . ";\n" : '';

        return [$namespace, $className];
    }

    /**
     * Indent code with spaces.
     * {@internal PSR-2 requires the use of 4 spaces for indentation. Other variants like tabs will not be supported.}}
     *
     * @param string $code
     * @param int    $spaces
     * @return string
     */
    final protected static function indent(string $code, int $spaces = 4): string
    {
        $indentation = str_repeat(' ', $spaces);

        return $indentation . str_replace("\n", "\n" . $indentation, $code);
    }
}
