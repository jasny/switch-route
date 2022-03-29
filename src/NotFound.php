<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use RuntimeException;

/**
 * Exception thrown if no route is found and there is no default route specified.
 */
class NotFound extends RuntimeException
{
}
