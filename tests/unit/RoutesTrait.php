<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\Endpoint;

trait RoutesTrait
{
    protected function getRoutes(): array
    {
        return [
            '  GET    /  '                => ['controller' => 'InfoController'],

            'GET      /users'             => ['controller' => 'UserController', 'action' => 'listAction'],
            'POST     /users'             => ['controller' => 'UserController', 'action' => 'addAction'],
            'GET      /users/{id}'        => ['controller' => 'UserController', 'action' => 'getAction'],
            'POST|PUT /users/{id}'        => ['controller' => 'UserController', 'action' => 'updateAction'],
            'DELETE   /users/{id}'        => ['controller' => 'UserController', 'action' => 'deleteAction'],

            'default'                     => ['action' => 'NotFoundAction'],

            'POST     /export'            => ['include' => 'scripts/export.php'],

            'GET      /users/{id}/photos' => ['action' => 'ListPhotosAction'],
            'POST     /users/{id}/photos' => ['action' => 'AddPhotosAction'],
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

        $structure["\0"] = $structure["\0"]->withRoute('GET', ['controller' => 'InfoController'], []);

        $structure["users"]["\0"] = $structure["users"]["\0"]
            ->withRoute('GET', ['controller' => 'UserController', 'action' => 'listAction'], [])
            ->withRoute('POST', ['controller' => 'UserController', 'action' => 'addAction'], []);

        $structure["users"]["*"]["\0"] = $structure["users"]["*"]["\0"]
            ->withRoute('GET', ['controller' => 'UserController', 'action' => 'getAction'], ['id' => 1])
            ->withRoute('POST', ['controller' => 'UserController', 'action' => 'updateAction'], ['id' => 1])
            ->withRoute('PUT', ['controller' => 'UserController', 'action' => 'updateAction'], ['id' => 1])
            ->withRoute('DELETE', ['controller' => 'UserController', 'action' => 'deleteAction'], ['id' => 1]);

        $structure["users"]["*"]["photos"]["\0"] = $structure["users"]["*"]["photos"]["\0"]
            ->withRoute('GET', ['action' => 'ListPhotosAction'], ['id' => 1])
            ->withRoute('POST', ['action' => 'AddPhotosAction'], ['id' => 1]);

        $structure["export"]["\0"] = $structure["export"]["\0"]
            ->withRoute('POST', ['include' => 'scripts/export.php'], []);

        $structure["\e"] = $structure["\e"]->withRoute('', ['action' => 'NotFoundAction'], []);

        return $structure;
    }
}
