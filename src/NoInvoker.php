<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

/**
 * Null object for returning rather invoking the action or script specified by the route.
 */
class NoInvoker implements InvokerInterface
{
    /**
     * Generate code for a function or method class for the route.
     *
     * The argument template should have two '%s' placeholders. The first is used for the argument name, the second for
     *   the default value.
     *
     * @param array    $route
     * @param callable $genArg  Callback to generate code for arguments.
     * @param string   $new     Unused
     * @return string
     */
    public function generateInvocation(array $route, callable $genArg, string $new = ''): string
    {
        return '[200, ' . var_export($route, true) . ', ' . $genArg(null) . ']';
    }

    /**
     * Generate standard code for when no route matches.
     */
    public function generateDefault(): string
    {
        return 'return $allowedMethods === [] ? [404] : [405, $allowedMethods];';
    }
}
