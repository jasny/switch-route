<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

trait RoutesTrait
{
    public static function getRoutes(): array
    {
        return [
            'GET      /'                  => ['controller' => 'info'],

            'GET      /users'             => ['controller' => 'user', 'action' => 'list'],
            'POST     /users'             => ['controller' => 'user', 'action' => 'add'],
            'GET      /users/{id}'        => ['controller' => 'user', 'action' => 'get'],
            'POST|PUT /users/{id}'        => ['controller' => 'user', 'action' => 'update'],
            'DELETE   /users/{id}'        => ['controller' => 'user', 'action' => 'delete'],

            'GET      /users/{id}/photos' => ['action' => 'list-photos'],
            'POST     /users/{id}/photos' => ['action' => 'add-photos'],

            'POST     /export'            => ['include' => __DIR__ . '/scripts/export.php'],

            'default'                   => ['action' => 'not-found'],
        ];
    }
}
