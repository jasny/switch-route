<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\Endpoint;

trait RoutesTrait
{
    protected function getRoutes(): array
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

            'POST     /export'            => ['include' => 'scripts/export.php'],

            'default'                     => ['action' => 'not-found'],
        ];
    }

    protected function getStructure(): array
    {
        $structure = [
            "\0" => new Endpoint("/"),
            "users" => [
                "\0" => new Endpoint("/users"),
                "*" => [
                    "\0" => new Endpoint("/users/*"),
                    "photos" => [
                        "\0" => new Endpoint("/users/*/photos"),
                    ],
                ],
            ],
            "export" => [
                "\0" => new Endpoint("/export"),
            ],
            "\e" => new Endpoint(''),
        ];

        $structure["\0"] = $structure["\0"]->withRoute('GET', ['controller' => 'info'], []);

        $structure["users"]["\0"] = $structure["users"]["\0"]
            ->withRoute('GET', ['controller' => 'user', 'action' => 'list'], [])
            ->withRoute('POST', ['controller' => 'user', 'action' => 'add'], []);

        $structure["users"]["*"]["\0"] = $structure["users"]["*"]["\0"]
            ->withRoute('GET', ['controller' => 'user', 'action' => 'get'], ['id' => 1])
            ->withRoute('POST', ['controller' => 'user', 'action' => 'update'], ['id' => 1])
            ->withRoute('PUT', ['controller' => 'user', 'action' => 'update'], ['id' => 1])
            ->withRoute('DELETE', ['controller' => 'user', 'action' => 'delete'], ['id' => 1]);

        $structure["users"]["*"]["photos"]["\0"] = $structure["users"]["*"]["photos"]["\0"]
            ->withRoute('GET', ['action' => 'list-photos'], ['id' => 1])
            ->withRoute('POST', ['action' => 'add-photos'], ['id' => 1]);

        $structure["export"]["\0"] = $structure["export"]["\0"]
            ->withRoute('POST', ['include' => 'scripts/export.php'], []);

        $structure["\e"] = $structure["\e"]->withRoute('', ['action' => 'not-found'], []);

        return $structure;
    }
}
