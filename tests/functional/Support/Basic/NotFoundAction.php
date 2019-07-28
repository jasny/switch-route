<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Basic;

/**
 * Test action
 * @internal
 */
class NotFoundAction
{
    public function __invoke(array $allowedMethods)
    {
        return $allowedMethods === []
            ? "404 Not Found"
            : "405 Method Not Allowed (" . join(', ', $allowedMethods) . ')';
    }
}
