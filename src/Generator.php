<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use Jasny\SwitchRoute\Generator\GenerateFunction;
use Spatie\Regex\Regex;

/**
 * Generate a PHP script for HTTP routing.
 */
class Generator
{
    private const DIR_MODE = 0755;

    protected $generateCode;


    /**
     * Generator constructor.
     *
     * @param callable $generate  Callback to generate code from structure.
     */
    public function __construct(callable $generate = null)
    {
        $this->generateCode = $generate ?? new GenerateFunction();
    }

    /**
     * Generate a switch script based on the routes.
     *
     * @param string   $name       Class or function name that should be generated.
     * @param string   $file       Filename to store the script.
     * @param callable $getRoutes  Callback to get an array with the routes.
     * @param bool     $overwrite  Overwrite existing file.
     * @throws \RuntimeException if file could not be created.
     */
    public function generate(string $name, string $file, callable $getRoutes, bool $overwrite): void
    {
        if (!$overwrite && $this->scriptExists($file)) {
            return;
        }

        $routes = $getRoutes();
        $structure = $this->structureEndpoints($routes);

        $code = ($this->generateCode)($name, $routes, $structure);
        if (!is_string($code)) {
            throw new \LogicException("Expected code as string, got " . gettype($code));
        }

        if (!is_dir(dirname($file))) {
            $this->tryFs('mkdir', dirname($file), self::DIR_MODE, true);
        }

        $this->tryFs('file_put_contents', $file, $code);
    }

    /**
     * Try a file system function and throw a \RuntimeException on failure.
     *
     * @param callable $fn
     * @param mixed    ...$args
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
            throw new \RuntimeException(error_get_last()['message'] ?? "Unknown error");
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
        /** @noinspection PhpComposerExtensionStubsInspection */
        return (function_exists('opcache_is_script_cached') && opcache_is_script_cached($file))
            || file_exists($file);
    }

    /**
     * Create a structure with a leaf for each endpoint.
     *
     * @param iterable $routes
     * @return array
     * @throws InvalidRouteException
     */
    protected function structureEndpoints(iterable $routes): array
    {
        $structure = [];

        foreach ($routes as $key => $route) {
            if ($key === 'default') {
                $structure["\e"] = (new Endpoint(''))->withRoute('', $route, []);
                continue;
            }

            $match = Regex::match('~^\s*(?P<methods>\w+(?:\|\w+)*)\s+(?P<path>/\S*)\s*$~', $key);

            if (!is_string($key) || !$match->hasMatch()) {
                throw new InvalidRouteException("Invalid routing key '$key': should be 'METHOD /path'");
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
}
