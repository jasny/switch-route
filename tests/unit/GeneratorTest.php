<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\InvalidRouteException;
use Jasny\TestHelper;
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
    use TestHelper;
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
        $path = vfsStream::url('tmp/generated/routes.php');

        $generator = new Generator($generate);
        $generator->generate('FortyTwo', $path, $getRoutes, false);

        $this->assertFileExists($path);
        $this->assertEquals($script, file_get_contents($path));
//        self::assertEquals(0666, fileperms($path) & 0777);
//        self::assertEquals(0755, fileperms('/tmp/generated') & 0777);
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
        $this->expectException(InvalidRouteException::class);
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
        $this->expectExceptionMessage("file_put_contents(vfs://tmp/generated/routes.php): failed to open stream");

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

    /**
     * extend class and check if Generator protected methods is available
     */
    public function testMethodsVisibility()
    {
        $extendedGenerator = new ExtendedGenerator();
        $extendedGenerator->testMethodsVisibility();
        self::assertTrue(true);
    }

    public function testStructureEndpoints()
    {
        $extendedGenerator = new ExtendedGenerator();
        $routes = [
            'default' => 'default',
            'GET /test' => 'test'
        ];
        $structuredEndpoints = $extendedGenerator->getStructuredEndpoints($routes);
        /** @var Endpoint $testEndpoint */
        $testEndpoint = array_shift($structuredEndpoints['test']);
        self::assertSame('/test', $testEndpoint->getPath());
        self::assertTrue(in_array('GET', $testEndpoint->getAllowedMethods()));
        self::assertSame(1, count($testEndpoint->getAllowedMethods()));
    }
}

class ExtendedGenerator extends Generator
{
    public function testMethodsVisibility()
    {
        $this->tryFs(function () {
        }, []);
        $this->scriptExists('tmp/generated/routes.php');
        $this->structureEndpoints([]);
        $this->splitPath('path');
    }

    public function getStructuredEndpoints(array $routes)
    {
        return $this->structureEndpoints($routes);
    }
}
