<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Generator;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\SwitchRoute\Invoker;
use ReflectionException;

/**
 * Generate a script that invokes .
 */
class GenerateScript extends AbstractGenerate
{
    /**
     * @var Invoker
     */
    protected $invoker;

    /**
     * PHP code to get the HTTP method.
     * @var string
     */
    protected $method;

    /**
     * PHP code to get the HTTP request path.
     * @var string
     */
    protected $path;

    /**
     * GenerateScript constructor.
     *
     * @param Invoker $invoker
     * @param string  $method    PHP code to get the HTTP method.
     * @param string  $path      PHP code to get the HTTP request path.
     */
    public function __construct(
        Invoker $invoker = null,
        string $method = '$_SERVER["REQUEST_METHOD"]',
        string $path = 'rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))'
    ) {
        $this->invoker = $invoker ?? new Invoker();

        $this->method = $method;
        $this->path = $path;
    }

    /**
     * Invoke code generation.
     *
     * @param string $class     Ignored
     * @param array  $routes    Ignored
     * @param array  $structure
     * @return string
     */
    public function __invoke(string $class, array $routes, array $structure): string
    {
        $default = $structure['*'] ?? null;
        unset($structure['*']);

        return join("\n", [
            '<?php',
            '',
            '$method = ' . $this->method . ';',
            '$path = ' . $this->path . ';',
            '$segments = $path === "/" ? [] : explode("/", trim($path, "/"));',
            '$allowedMethods = [];',
            '',
            $this->generateSwitch($structure),
            '',
            $this->generateDefault($default),
        ]);
    }

    /**
     * Generate code for an endpoint
     *
     * @param Endpoint $endpoint
     * @return string
     */
    protected function generateEndpoint(Endpoint $endpoint): string
    {
        $exportValue = function ($var) {
            return var_export($var, true);
        };

        return join("\n", [
            "\$allowedMethods = [" . join(', ', array_map($exportValue, $endpoint->getAllowedMethods())) . "];",
            parent::generateEndpoint($endpoint)
        ]);
    }

    /**
     * Generate routing code for an endpoint.
     *
     * @param string        $key
     * @param array         $route
     * @param array         $vars
     * @param callable|null $genArg
     * @return string
     * @throws InvalidRouteException
     */
    protected function generateRoute(string $key, array $route, array $vars, ?callable $genArg = null): string
    {
        if (!isset($route['include']) && !isset($route['controller']) && !isset($route['action'])) {
            throw new InvalidRouteException("Route for '$key' should specify 'include', 'controller', " .
                "or 'action'");
        }

        if (isset($route['include'])) {
            return "return require '" . addslashes($route['include']) . "';";
        }

        try {
            $invocation = $this->invoker->generateInvocation(
                $route['controller'] ?? null,
                $route['action'] ?? null,
                $genArg ?? function ($name, $default) use ($vars) {
                    return isset($vars[$name]) ? "\$segments[{$vars[$name]}]" : var_export($default, true);
                }
            );
        } catch (ReflectionException $exception) {
            throw new InvalidRouteException("Invalid route for '$key'. ". $exception->getMessage(), 0, $exception);
        }

        return "return $invocation;";
    }

    /**
     * Generate code for when no route matches.
     *
     * @param Endpoint|null $endpoint
     * @return string
     * @throws InvalidRouteException
     */
    protected function generateDefault(?Endpoint $endpoint): string
    {
        if ($endpoint === null) {
            return <<<CODE
if (\$allowedMethods === []) {
    http_response_code(404);
    echo "Not Found";
} else {
    http_response_code(405);
    header('Allow: ' . join(', ', \$allowedMethods));
    echo "Method Not Allowed";
}
CODE;
        }

        $genArg = function ($name, $default) {
            return "\${$name} ?? " . var_export($default, true);
        };

        return $this->generateRoute('default', $endpoint->getRoutes()[''], [], $genArg);
    }
}
