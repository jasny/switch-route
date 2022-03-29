<?php

namespace Jasny\SwitchRoute\Tests;

use Closure;
use Exception;
use Jasny\ReflectionFactory\ReflectionFactory;
use Jasny\ReflectionFactory\ReflectionFactoryInterface;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Generator\GenerateFunction;
use Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware;
use Jasny\SwitchRoute\Generator\GenerateRouteMiddleware;
use Jasny\SwitchRoute\InvalidRoute;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\InvokerInterface;
use Jasny\SwitchRoute\NotFoundMiddleware;
use Jasny\SwitchRoute\Routes;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class ExtendTest extends TestCase
{
    use RoutesTrait;

    public function testExtendedGenerator()
    {
        $extendedGenerator = $this->initExtendedGenerator();

        /** structureEndpoints */
        $structuredEndpoints = $extendedGenerator->callStructureEndpoints($this->getRoutes());
        /** @var Endpoint $testEndpoint */
        $testEndpoint = array_shift($structuredEndpoints['users']);
        $this->assertSame('/users', $testEndpoint->getPath());
        $this->assertTrue(in_array('GET', $testEndpoint->getAllowedMethods()));
        $this->assertTrue(in_array('POST', $testEndpoint->getAllowedMethods()));
        $this->assertSame(2, count($testEndpoint->getAllowedMethods()));

        /** splitPath */
        $splitPath = $extendedGenerator->callSplitPath('/users/*/display');
        $this->assertEquals([['users', '*', 'display'], []], $splitPath);

        /** scriptExistsGenerator */
        $scriptExists = $extendedGenerator->callScriptExists('routes.php');
        $this->assertFalse($scriptExists);

        /** tryFs */
        $tryFsResult = $extendedGenerator->callTryFs('disk_free_space', '.');
        $this->assertIsFloat($tryFsResult);
    }

    /** Invoker */

    public function testExtendedInvoker()
    {
        $extendedInvoker = $this->initExtendedInvoker();

        /** generateInvocationArgs */
        $reflection = new ReflectionFunction('time');
        $genArg = function () {
        };
        $this->assertEmpty($extendedInvoker->callGenerateInvocationArgs($reflection, $genArg));

        /** generateInvocationMethod */
        $invokable = ['DateTime', 'format'];
        $reflection = new ReflectionMethod('DateTime', 'format');
        $new = '(new \\%s)';
        $this->assertSame(
            '(new \DateTime)->format',
            $extendedInvoker->callGenerateInvocationMethod($invokable, $reflection, $new)
        );

        /** assertInvokable */
        $invokable = ['DateTime', 'format'];
        $this->assertNull($extendedInvoker->callAssertInvokable($invokable));

        /** getReflection */
        $invokable = ['DateTime', 'format'];
        $reflection = $extendedInvoker->callGetReflection($invokable);
        $this->assertInstanceOf(ReflectionMethod::class, $reflection);
        $this->assertTrue(isset($reflection->name));
        $this->assertSame('format', $reflection->name);
        $this->assertTrue(isset($reflection->class));
        $this->assertSame('DateTime', $reflection->class);

        /** reflectionProperty */
        $this->assertInstanceOf(ReflectionFactory::class, $extendedInvoker->getReflectionProperty());

        /** createInvokableProperty */
        $this->assertInstanceOf(Closure::class, $extendedInvoker->getCreateInvokableProperty());
    }

    /** NotFoundMiddleware */

    public function testExtendedNotFoundMiddleware()
    {
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->any())->method('createResponse')->with($this->anything())
            ->willReturn(new Response());
        $extendedNotFoundMiddleware = $this->initExtendedNotFoundMiddleware($responseFactory);

        /** notFound */
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getAttribute')
            ->with('route:allowed_methods')
            ->willReturn(['GET', 'POST']);
        $this->assertInstanceOf(ResponseInterface::class, $extendedNotFoundMiddleware->callNotFound($request));

        /** createInvokableProperty */
        $this->assertInstanceOf(
            ResponseFactoryInterface::class,
            $extendedNotFoundMiddleware->getCreateInvokableProperty()
        );
    }

    /**  */

    public function testExtendedGenerateFunction()
    {
        $extendedGenerateFunction = $this->initExtendedGenerateFunction();

        /** generateNs */
        $this->assertEquals(['', 'Dummy'], $extendedGenerateFunction->callGenerateNs('Dummy'));

        /** genArg */
        $this->assertEquals(
            '["id" => $segments[1], "test" => $segments[1]]',
            $extendedGenerateFunction->callGenArg(['id' => 1, 'test' => true], null)
        );
        $this->assertEquals(
            'NULL',
            $extendedGenerateFunction->callGenArg(['id' => 1, 'test' => true], 'null')
        );
        $this->assertEquals(
            '$segments[1]',
            $extendedGenerateFunction->callGenArg(['id' => 1, 'test' => true], 'id')
        );

        /** generateDefault */
        $this->assertNotEmpty($extendedGenerateFunction->callGenerateDefault(null));

        /** generateSwitch */
        $this->assertEquals(
            'switch ($segments[0] ?? "\0") {' . PHP_EOL . '}',
            $extendedGenerateFunction->callGenerateSwitch([])
        );

        /** generateRoute */
        try {
            $extendedGenerateFunction->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [], null);
        } catch (InvalidRoute $exception) {
            $this->assertTrue(true);
        } catch (Exception $exception) {
            $this->assertTrue(false, $exception->getMessage());
        }

        /** generateEndpoint */
        $this->assertSame(
            '$allowedMethods = [];' . PHP_EOL . 'switch ($method) {' . PHP_EOL . '}',
            $extendedGenerateFunction->callGenerateEndpoint(new Endpoint('/path'))
        );

        /** invokerProperty */
        $this->assertInstanceOf(InvokerInterface::class, $extendedGenerateFunction->getInvokerProperty());
    }

    /**  */

    public function testExtendedGenerateInvokeMiddleware()
    {
        $extendedGenerateInvokeMiddleware = $this->initExtendedGenerateInvokeMiddleware();

        /** generateNs */
        $this->assertEquals(['', 'Dummy'], $extendedGenerateInvokeMiddleware->callGenerateNs('Dummy'));

        /** generateSwitch */
        $this->assertEquals(
            'switch ($segments[0] ?? "\0") {' . PHP_EOL . '}',
            $extendedGenerateInvokeMiddleware->callGenerateSwitch([])
        );

        /** generateRoute  */
        try {
            $extendedGenerateInvokeMiddleware->callGenerateRoute('POST /path', ['controller' => 'Dummy'], []);
        } catch (InvalidRoute $exception) {
            $this->assertTrue(true);
        } catch (Exception $exception) {
            $this->assertTrue(false, $exception->getMessage());
        }

        /** generateEndpoint */
        $this->assertSame(
            'switch ($method) {' . PHP_EOL . '}',
            $extendedGenerateInvokeMiddleware->callGenerateEndpoint(new Endpoint('/path'))
        );

        /** groupRoutes */
        $this->assertSame(
            [],
            $extendedGenerateInvokeMiddleware->callGroupRoutes(new Routes())
        );

        /** generateSwitchFromRoutes */
        $this->assertNotEmpty($extendedGenerateInvokeMiddleware->callGenerateSwitchFromRoutes(new Routes()));

        /** invokerProperty */
        $this->assertInstanceOf(InvokerInterface::class, $extendedGenerateInvokeMiddleware->getInvokerProperty());
    }

    public function testExtendedGenerateRouteMiddleware()
    {
        $extendedGenerateRouteMiddleware = $this->initExtendedGenerateRouteMiddleware();

        /** generateNs */
        $this->assertEquals(['', 'Dummy'], $extendedGenerateRouteMiddleware->callGenerateNs('Dummy'));

        /** generateDefault */
        $this->assertNotEmpty($extendedGenerateRouteMiddleware->callGenerateDefault(null));

        /** generateSwitch */
        $this->assertEquals(
            'switch ($segments[0] ?? "\0") {' . PHP_EOL . '}',
            $extendedGenerateRouteMiddleware->callGenerateSwitch([])
        );

        /** generateRoute */
        $this->assertSame(
            'return $request' . PHP_EOL . '    ->withAttribute(\'route:controller\', \'Dummy\');',
            $extendedGenerateRouteMiddleware->callGenerateRoute('POST /path', ['controller' => 'Dummy'], [])
        );

        /** generateEndpoint */
        $this->assertSame(
            '$request = $request->withAttribute(\'route:allowed_methods\', []);'
                . PHP_EOL . 'switch ($method) {' . PHP_EOL . '}',
            $extendedGenerateRouteMiddleware->callGenerateEndpoint(new Endpoint('/path'))
        );
    }

    private function initExtendedGenerator()
    {
        return new class extends Generator
        {
            public function callStructureEndpoints(array $routes): array
            {
                return $this->structureEndpoints($routes);
            }

            public function callSplitPath(string $path): array
            {
                return $this->splitPath($path);
            }

            public function callScriptExists(string $file): bool
            {
                return $this->scriptExists($file);
            }

            public function callTryFs(callable $fn, ...$args)
            {
                return $this->tryFs($fn, ...$args);
            }
        };
    }

    private function initExtendedGenerateFunction()
    {
        return new class extends GenerateFunction
        {
            public function callGenerateNs(string $class): array
            {
                return $this->generateNs($class);
            }

            public function callGenArg(array $vars, ?string $name, ?string $type = null, $default = null): string
            {
                return $this->genArg($vars, $name, $type, $default);
            }

            public function callGenerateDefault(?Endpoint $endpoint): string
            {
                return $this->generateDefault($endpoint);
            }

            public function callGenerateSwitch(array $structure, int $level = 0): string
            {
                return $this->generateSwitch($structure, $level);
            }

            public function callGenerateRoute(string $key, array $route, array $vars, ?callable $genArg = null): string
            {
                return $this->generateRoute($key, $route, $vars, $genArg);
            }

            public function callGenerateEndpoint(Endpoint $endpoint): string
            {
                return $this->generateEndpoint($endpoint);
            }

            public function getInvokerProperty(): InvokerInterface
            {
                return $this->invoker;
            }
        };
    }

    private function initExtendedGenerateInvokeMiddleware()
    {
        return new class extends GenerateInvokeMiddleware
        {
            public function callGenerateNs(string $class): array
            {
                return $this->generateNs($class);
            }

            public function callGenerateSwitch(array $structure, int $level = 0): string
            {
                return $this->generateSwitch($structure, $level);
            }

            public function callGenerateRoute(string $key, array $route, array $vars): string
            {
                return $this->generateRoute($key, $route, $vars);
            }

            public function callGenerateEndpoint(Endpoint $endpoint): string
            {
                return $this->generateEndpoint($endpoint);
            }

            public function callGroupRoutes(Routes $routes): array
            {
                return $this->groupRoutes($routes);
            }

            public function callGenerateSwitchFromRoutes(Routes $routes): string
            {
                return $this->generateSwitchFromRoutes($routes);
            }

            public function getInvokerProperty(): InvokerInterface
            {
                return $this->invoker;
            }
        };
    }

    private function initExtendedGenerateRouteMiddleware()
    {
        return new class extends GenerateRouteMiddleware
        {
            public function callGenerateNs(string $class): array
            {
                return $this->generateNs($class);
            }

            public function callGenerateDefault(?Endpoint $endpoint): string
            {
                return $this->generateDefault($endpoint);
            }

            public function callGenerateSwitch(array $structure, int $level = 0): string
            {
                return $this->generateSwitch($structure, $level);
            }

            public function callGenerateRoute(string $key, array $route, array $vars): string
            {
                return $this->generateRoute($key, $route, $vars);
            }

            public function callGenerateEndpoint(Endpoint $endpoint): string
            {
                return $this->generateEndpoint($endpoint);
            }
        };
    }

    private function initExtendedInvoker()
    {
        return new class extends Invoker
        {
            public function callGenerateInvocationArgs(ReflectionFunctionAbstract $reflection, callable $genArg): string
            {
                return $this->generateInvocationArgs($reflection, $genArg);
            }

            public function callGenerateInvocationMethod(
                array $invokable,
                ReflectionMethod $reflection,
                string $new = '(new \\%s)'
            ): string {
                return $this->generateInvocationMethod($invokable, $reflection, $new);
            }

            public function callAssertInvokable($invokable): void
            {
                $this->assertInvokable($invokable);
            }

            public function callGetReflection($invokable): ReflectionFunctionAbstract
            {
                return $this->getReflection($invokable);
            }

            public function getReflectionProperty(): ?ReflectionFactoryInterface
            {
                return $this->reflection;
            }

            public function getCreateInvokableProperty()
            {
                return $this->createInvokable;
            }
        };
    }

    private function initExtendedNotFoundMiddleware(ResponseFactoryInterface $responseFactory)
    {
        return new class($responseFactory) extends NotFoundMiddleware
        {
            public function callNotFound(ServerRequestInterface $request): ResponseInterface
            {
                return $this->notFound($request);
            }

            public function getCreateInvokableProperty(): ResponseFactoryInterface
            {
                return $this->responseFactory;
            }
        };
    }
}
