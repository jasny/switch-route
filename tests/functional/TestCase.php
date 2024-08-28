<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base
{
    protected static array $users = [
        1 => ['id' => 1, 'name' => 'joe', 'email' => 'joe@example.com'],
        2 => ['id' => 2, 'name' => 'jane', 'email' => 'jane@example.com'],
    ];

    protected static array $photos = [
        1 => [
            ['id' => 11, 'name' => 'sunrise'],
            ['id' => 12, 'name' => 'sunset'],
        ],
        2 => [
            ['id' => 13, 'name' => 'red'],
            ['id' => 14, 'name' => 'green'],
            ['id' => 15, 'name' => 'blue'],
        ]
    ];

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

            'default'                     => ['action' => 'not-found'],
        ];
    }

    public static function provider(): array
    {
        return [
            'GET    /'                => ['GET', '/', "Some information"],

            'GET    /users'           => ['GET', '/users', array_values(self::$users)],
            'POST   /users'           => ['POST', '/users', "added user"],
            'GET    /users/1'         => ['GET', '/users/1', self::$users[1]],
            'GET    /users/2'         => ['GET', '/users/2', self::$users[2]],
            'POST   /users/1'         => ['POST', '/users/1', "updated user '1'"],
            'PUT    /users/1'         => ['PUT', '/users/1', "updated user '1'"],
            'DELETE /users/1'         => ['DELETE', '/users/2', "deleted user '2'"],

            'GET    /users/1/photos'  => ['GET', '/users/1/photos', self::$photos[1]],
            'GET    /users/2/photos'  => ['GET', '/users/2/photos', self::$photos[2]],
            'POST   /users/1/photos'  => ['POST', '/users/1/photos', "added photos for user 1"],

            'POST   /export'          => ['POST', '/export', ['data', 'export']],

            'POST   /foo (404)'       => ['POST', '/foo', "404 Not Found", 404],
            'DELETE /users (405)'     => ['DELETE', '/users', "405 Method Not Allowed (GET, POST)", 405],
            'PATCH  /users/1 (405)'   => ['PATCH', '/users/1', "405 Method Not Allowed (GET, POST, PUT, DELETE)", 405],

            // Test with trailing slash
            'GET    /users/'          => ['GET', '/users/', array_values(self::$users)],
            'GET    /users/2/'        => ['GET', '/users/2/', self::$users[2]],
            'GET    /users/2/photos/' => ['GET', '/users/2/photos/', self::$photos[2]],
        ];
    }
}
