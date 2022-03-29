<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use UnexpectedValueException;
use Jasny\SwitchRoute\Generator\AbstractGenerate;
use Jasny\SwitchRoute\Generator\GenerateFunction;

/**
 * Generate a PHP script for HTTP routing.
 */
class Generator
{
    private const DIR_MODE = 0755;

    /**
     * @var callable|AbstractGenerate
     */
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
     * @param string   $name            Class or function name that should be generated.
     * @param string   $file            Filename to store the script.
     * @param callable $routesCallback  Callback to get an array with the routes.
     * @param bool     $overwrite       Overwrite existing file.
     * @throws \RuntimeException if file could not be created.
     */
    public function generate(string $name, string $file, callable $routesCallback, bool $overwrite): void
    {
        if (!$overwrite && $this->scriptExists($file)) {
            return;
        }

        $routes = $this->loadRoutes($routesCallback);

        $code = ($this->generateCode)($name, $routes);
        if (!is_string($code)) {
            throw new \LogicException("Expected code as string, got " . gettype($code));
        }

        if (!is_dir(dirname($file))) {
            $this->tryFs(fn() => mkdir(dirname($file), self::DIR_MODE, true));
        }

        $this->tryFs(fn() => file_put_contents($file, $code));
    }

    /**
     * Load the routes via the callback
     *
     * @param callable $callback
     * @return Routes
     */
    public function loadRoutes(callable $callback): Routes
    {
        $routes = new Routes();
        $ret = $callback($routes);

        if (!isset($ret)) {
            // Callback configured routes
        } elseif (!is_iterable($ret)) {
            throw new UnexpectedValueException("Callback to get routes didn't return an array");
        } else {
            $routes->add($ret);
        }

        return $routes;
    }

    /**
     * Try a file system function and throw a \RuntimeException on failure.
     *
     * @param callable $fn
     * @return mixed
     */
    protected function tryFs(callable $fn)
    {
        $level = error_reporting();
        error_reporting($level ^ (E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE));

        error_clear_last();

        try {
            $ret = $fn();
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
}
