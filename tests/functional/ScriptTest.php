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
class ScriptTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected static $root;

    /**
     * @var string
     */
    protected static $file;

    public static function setUpBeforeClass(): void
    {
        self::$root = vfsStream::setup('tmp');
        self::$file = vfsStream::url('tmp/generated/routes.php');

        $invoker = new Invoker(function ($class, $action) {
            [$class, $method] = Invoker::createInvokable($class, $action);
            return [__NAMESPACE__ . '\\Support\\Basic\\' . $class, $method];
        });

        $generator = new Generator(new Generator\GenerateScript($invoker, '$method', '$path'));
        $generator->generate('', self::$file, [__CLASS__, 'getRoutes']);
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
        $result = require self::$file;

        $this->assertEquals($expected, $result);
    }
}
