<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use Jasny\SwitchRoute\Generator\GenerateScript;
use LogicException;
use RuntimeException;

/**
 * Generate a PHP script for HTTP routing.
 */
class Generator
{
    /**
     * @var callable
     */
    protected $generateCode;


    /**
     * Generator constructor.
     *
     * @param callable $generate  Callback to generate code from structure.
     */
    public function __construct(callable $generate = null)
    {
        $this->generateCode = $generate ?? new GenerateScript();
    }

    /**
     * Generate a switch script based on the routes.
     *
     * @param string   $class      Class name that should be generated (empty string if no class).
     * @param string   $file       Filename to store the script.
     * @param callable $getRoutes  Callback to get an array with the routes.
     * @param bool     $force      Always generate a new script, even if it already exists.
     * @throws RuntimeException if file could not be created.
     */
    public function generate(string $class, string $file, callable $getRoutes, bool $force = false): void
    {
        if (!$force && $this->scriptExists($file)) {
            return;
        }

        $routes = $getRoutes();
        $structure = $this->structureEndpoints($routes);

        $code = ($this->generateCode)($class, $routes, $structure);
        if (!is_string($code)) {
            throw new LogicException("Expected code as string, got " . gettype($code));
        }

        if (!is_dir(dirname($file))) {
            $this->tryFs('mkdir', dirname($file), 0755, true);
        }

        $this->tryFs('file_put_contents', $file, $code);
    }

    /**
     * Try a file system function and throw a RuntimeException on failure.
     *
     * @param callable $fn
     * @param mixed ...$args
     * @return mixed
     */
    protected function tryFs(callable $fn, ...$args)
    {
        $level = error_reporting();
        error_reporting($level ^ (E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE));

        error_clear_last();

        try {
            $ret = $fn(...$args);
        } finally {
            error_reporting($level);
        }

        if ($ret === false) {
            throw new RuntimeException(error_get_last()['message']);
        }

        return $ret;
    }

    /**
     * Check if the script exists.
     * Uses `opcache_is_script_cached` to prevent an unnecessary filesystem read.
     *
     * {@internal opcache isn't easily testable and mocking `opcache_is_script_cached` doesn't seem that useful.}}
     *
     * @param string $file
     * @return bool
     */
    protected function scriptExists(string $file): bool
    {
        return opcache_is_script_cached($file) || file_exists($file);
    }

    /**
     * Create a structure with a leaf for each endpoint.
     *
     * @param array $routes
     * @return array
     */
    protected function structureEndpoints(array $routes): array
    {
        $structure = [];

        foreach ($routes as $key => $route) {
            if ($key === 'default') {
                $structure["*"] = (new Endpoint(''))->withRoute('', $routes['default'], []);
                continue;
            }

            if (!preg_match('~^\w+(?:\|\w+)*\s+/\S*$~', $key)) {
                throw new InvalidRouteException("Invalid routing key '$key': should be 'METHOD /path'");
            }

            [$methods, $varPath] = preg_split('~\s++~', trim($key), 2);
            [$segments, $vars] = $this->splitPath($varPath);

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
     * @return array{array, array}
     */
    protected function splitPath(string $path): array
    {
        if ($path === '/') {
            return [[], []];
        }

        $segments = explode('/', substr($path, 1));
        $vars = [];

        foreach ($segments as $index => &$segment) {
            if (preg_match('/^(?|:(?P<var>\w+)|\{(?P<var>\w+)\})$/', $segment, $match)) {
                $vars[$match['var']] = $index;
                $segment = '*';
            }
        }

        return [$segments, $vars];
    }
}
