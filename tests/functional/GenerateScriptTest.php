<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class GenerateScriptTest extends TestCase
{
    use RoutesTrait;

    /**
     * @var vfsStreamDirectory
     */
    protected static $root;

    /**
     * @var string
     */
    protected static $file;

    protected $users = [
        1 => ['id' => 1, 'name' => 'joe', 'email' => 'joe@example.com'],
        2 => ['id' => 2, 'name' => 'jane', 'email' => 'jane@example.com'],
    ];

    protected $photos = [
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

    public static function setUpBeforeClass(): void
    {
        self::$root = vfsStream::setup('tmp');
        self::$file = vfsStream::url('tmp/generated/routes.php');

        $invoker = new Invoker(function ($class, $action) {
            [$class, $method] = Invoker::createInvokable($class, $action);
            return [__NAMESPACE__ . '\\Support\\' . $class, $method];
        });

        $generator = new Generator(new Generator\GenerateScript($invoker, '$method', '$path'));
        $generator->generate('', self::$file, [__CLASS__, 'getRoutes']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$root = null;
    }

    public function provider()
    {
        return [
            ['GET', '/', "Some information"],

            ['GET', '/users', array_values($this->users)],
            ['POST', '/users', "added user"],
            ['GET', '/users/1', $this->users[1]],
            ['GET', '/users/2', $this->users[2]],
            ['POST', '/users/1', "updated user '1'"],
            ['PUT', '/users/1', "updated user '1'"],
            ['DELETE', '/users/2', "deleted user '2'"],

            ['GET', '/users/1/photos', $this->photos[1]],
            ['GET', '/users/2/photos', $this->photos[2]],
            ['POST', '/users/1/photos', "added photos for user 1"],

            ['POST', '/export', ['data', 'export']],

            ['POST', '/foo', "404 Not Found"],
            ['DELETE', '/users', "405 Method Not Allowed (GET, POST)"],
            ['PATCH', '/users/1', "405 Method Not Allowed (GET, POST, PUT, DELETE)"],

            // Test with trailing slash
            ['GET', '/users/', array_values($this->users)],
            ['GET', '/users/2/', $this->users[2]],
            ['GET', '/users/2/photos/', $this->photos[2]],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test(string $method, string $path, $expected)
    {
        $result = require self::$file;

        $this->assertEquals($expected, $result);
    }
}
