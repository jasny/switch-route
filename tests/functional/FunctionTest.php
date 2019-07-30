<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests;

use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Invoker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @coversNothing
 */
class FunctionTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected static $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = vfsStream::setup('tmp');
        $file = vfsStream::url('tmp/generated/routes.php');

        $invoker = new Invoker(function ($class, $action) {
            [$class, $method] = Invoker::createInvokable($class, $action);
            return [__NAMESPACE__ . '\\Support\\Basic\\' . $class, $method];
        });

        $generator = new Generator(new Generator\GenerateFunction($invoker));
        $generator->generate('route', $file, [__CLASS__, 'getRoutes'], true);

        require $file;
    }

    public static function tearDownAfterClass(): void
    {
        self::$root = null;
    }

    /**
     * @dataProvider provider
     */
    public function test(string $method, string $path, $expected)
    {
        $result = route($method, $path);

        $this->assertEquals($expected, $result);
    }
}
