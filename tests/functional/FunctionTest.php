<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
class FunctionTest extends TestCase
{
    protected static ?vfsStreamDirectory $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = vfsStream::setup('tmp');
        $file = vfsStream::url('tmp/generated/route.php');

        $invoker = new Invoker(function ($controller, $action) {
            [$class, $method] = $controller !== null
                ? [$controller . 'Controller', ($action ?? 'default') . 'Action']
                : [$action . 'Action', '__invoke'];

            return [
                __NAMESPACE__ . '\\Support\\Basic\\' . strtr(ucwords($class, '-'), ['-' => '']),
                strtr(lcfirst(ucwords($method, '-')), ['-' => ''])
            ];
        });

        $generator = new Generator(new Generator\GenerateFunction($invoker));
        $generator->generate(__NAMESPACE__ . '\\route', $file, [__CLASS__, 'getRoutes'], true);

        require $file;
    }

    public static function tearDownAfterClass(): void
    {
        self::$root = null;
    }

    #[DataProvider('provider')]
    public function test(string $method, string $path, $expected)
    {
        $fn = \Closure::fromCallable(__NAMESPACE__ . '\\route');
        $result = $fn($method, $path);

        $this->assertEquals($expected, $result);
    }
}
