<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use ReflectionException;

/**
 * Interface for route invoker.
 */
interface InvokerInterface
{
    /**
     * Generate code for a function or method class for the route.
     *
     * The argument template should have two '%s' placeholders. The first is used for the argument name, the second for
     *   the default value.
     *
     * @param array    $route
     * @param callable $genArg  Callback to generate code for arguments.
     * @param string   $new     PHP code to instantiate class.
     * @return string
     * @throws ReflectionException
     */
    public function generateInvocation(array $route, callable $genArg, string $new = '(new %s)'): string;

    /**
     * Generate standard code for when no route matches.
     *
     * @return string
     */
    public function generateDefault(): string;
}
