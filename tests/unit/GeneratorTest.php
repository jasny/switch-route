<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\InvalidRoute;
use Jasny\PHPUnit\CallbackMockTrait;
use LogicException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Jasny\SwitchRoute\Generator
 */
class GeneratorTest extends TestCase
{
    use CallbackMockTrait;
    use RoutesTrait;

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup('tmp');
    }

    public function testGenerate()
    {
        $script = '<?php return 42;';

        $routes = $this->getRoutes();
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);

        $expectStructure = $this->getStructure();
        $generate = $this->createCallbackMock($this->once(), ['FortyTwo', $routes, $expectStructure], $script);
        $path = vfsStream::url('tmp/generated/sub/routes.php');

        $generator = new Generator($generate);
        $generator->generate('FortyTwo', $path, $getRoutes, false);

        $this->assertFileExists($path);
        $this->assertEquals($script, file_get_contents($path));
    }

    public function testGenerateExistingScript()
    {
        vfsStream::create([
            'generated' => [
                'routes.php' => '<?php return 42;'
            ]
        ]);

        $getRoutes = $this->createCallbackMock($this->never());
        $generate = $this->createCallbackMock($this->never());
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('FortyTwo', $path, $getRoutes, false);
    }

    public function testGenerateOverwrite()
    {
        vfsStream::create([
            'generated' => [
                'routes.php' => '<?php return -1;'
            ]
        ]);

        $script = '<?php return 42;';

        $routes = $this->getRoutes();
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);

        $expectStructure = $this->getStructure();
        $generate = $this->createCallbackMock($this->once(), ['FortyTwo', $routes, $expectStructure], $script);
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('FortyTwo', $path, $getRoutes, true);

        $this->assertFileExists($path);
        $this->assertEquals($script, file_get_contents($path));
    }

    public function testGenerateWithInvalidRoute()
    {
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage("Invalid routing key '/foo': should be 'METHOD /path'");

        $routes = [
            '/foo' => ['action' => 'foo']
        ];
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);
        $generate = $this->createCallbackMock($this->never());

        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('foo', $path, $getRoutes, true);
    }

    public function testGenerateWithUnexpectedReturnValue()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expected code as string, got object");

        $routes = $this->getRoutes();
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);

        $expectStructure = $this->getStructure();
        $generate = $this->createCallbackMock($this->once(), ['foo', $routes, $expectStructure], (object)[]);
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('foo', $path, $getRoutes, true);
    }

    public function testGenerateCreateDirFailure()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("mkdir(): Path vfs://tmp/generated exists");

        vfsStream::create([
            'generated' => 'test'
        ]);

        $script = '<?php return 42;';

        $routes = $this->getRoutes();
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);

        $expectStructure = $this->getStructure();
        $generate = $this->createCallbackMock($this->once(), ['foo', $routes, $expectStructure], $script);
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('foo', $path, $getRoutes, true);
    }

    public function testGenerateCreateFileFailure()
    {
        $this->expectException(RuntimeException::class);

        vfsStream::newDirectory('generated', 00)->at($this->root);

        $script = '<?php return 42;';

        $routes = $this->getRoutes();
        $getRoutes = $this->createCallbackMock($this->once(), [], $routes);

        $expectStructure = $this->getStructure();
        $generate = $this->createCallbackMock($this->once(), ['foo', $routes, $expectStructure], $script);
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('foo', $path, $getRoutes, true);
    }
}
