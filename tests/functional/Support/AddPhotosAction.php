<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support;

/**
 * Test action
 * @internal
 */
class AddPhotosAction
{
    public function __invoke($id)
    {
        return "added photos for user $id";
    }
}
