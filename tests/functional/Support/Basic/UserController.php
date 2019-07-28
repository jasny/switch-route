<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support\Basic;

/**
 * Test controller
 * @internal
 */
class UserController
{
    protected $users = [
        1 => ['id' => 1, 'name' => 'joe', 'email' => 'joe@example.com'],
        2 => ['id' => 2, 'name' => 'jane', 'email' => 'jane@example.com'],
    ];

    public function listAction()
    {
        return array_values($this->users);
    }

    public function addAction()
    {
        return "added user";
    }

    public function getAction($id)
    {
        return $this->users[(int)$id];
    }

    public function updateAction($id)
    {
        return "updated user '$id'";
    }

    public function deleteAction($id)
    {
        return "deleted user '$id'";
    }
}
