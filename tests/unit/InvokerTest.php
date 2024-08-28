<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use BadMethodCallException;
use Jasny\ReflectionFactory\ReflectionFactory;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\Tests\Utils\CallbackMockTrait;
use Jasny\SwitchRoute\Tests\Utils\Consecutive;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

#[CoversClass(Invoker::class)]
class InvokerTest extends TestCase
{
    use CallbackMockTrait;

    /**
     * Create reflection factory that will return a ReflectionMethod.
     *
     * @param array $invokable
     * @param bool $isStatic
     * @param ReflectionParameter[] $parameters
     * @return ReflectionFactory&MockObject
     */
    protected function createReflectionFactoryMock($invokable, $isStatic, $parameters): ReflectionFactory
    {
        $reflection = $this->createMock(ReflectionMethod::class);
        $reflection->expects($this->any())->method('isStatic')->willReturn($isStatic);
        $reflection->expects($this->any())->method('getParameters')->willReturn($parameters);

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->once())->method('reflectMethod')
            ->with(...$invokable)->willReturn($reflection);
        $reflectionFactory->expects($this->never())->method('reflectFunction');

        return $reflectionFactory;
    }


    public static function invokableProvider()
    {
        return [
            "FooController::toDoAction" => [
                'FooController',
                'toDoAction',
                ['FooController', 'toDoAction'],
                "(new \FooController)->toDoAction(%s)"
            ],
            "ToDoController" => [
                'ToDoController',
                null,
                ['ToDoController', 'defaultAction'],
                "(new \ToDoController)->defaultAction(%s)"
            ],
            "QuxAction" =>[
                null,
                'QuxAction',
                ['QuxAction', '__invoke'],
                "(new \QuxAction)(%s)"
            ],
        ];
    }

    #[DataProvider('invokableProvider')]
    public function testGenerate($controller, $action, $invokable, $expected)
    {
        $reflectionFactory = $this->createReflectionFactoryMock($invokable, false, []);
        $genArg = $this->createCallbackMock($this->never());

        $invoker = new Invoker(null, $reflectionFactory);
        $code = $invoker->generateInvocation(compact('controller', 'action'), $genArg);

        $this->assertEquals(sprintf($expected, ''), $code);
    }

    #[DataProvider('invokableProvider')]
    public function testGenerateWithoutNullKeys($controller, $action, $invokable, $expected)
    {
        $reflectionFactory = $this->createReflectionFactoryMock($invokable, false, []);
        $genArg = $this->createCallbackMock($this->never());

        $invoker = new Invoker(null, $reflectionFactory);
        $code = $invoker->generateInvocation(array_filter(compact('controller', 'action')), $genArg);

        $this->assertEquals(sprintf($expected, ''), $code);
    }

    #[DataProvider('invokableProvider')]
    public function testGenerateWithArguments($controller, $action, $invokable, $expected)
    {
        $expectedCode = sprintf($expected, "\$var['id'] ?? NULL, \$var['some'] ?? NULL, \$var['good'] ?? 'ok'");

        $mixedType = $this->createMock(ReflectionType::class);
        $stringType = $this->createConfiguredMock(ReflectionNamedType::class, ['getName' => 'string']);
        $parameters = [
            $this->createConfiguredMock(
                ReflectionParameter::class,
                ['getName' => 'id', 'getType' => null, 'isOptional' => false]
            ),
            $this->createConfiguredMock(
                ReflectionParameter::class,
                ['getName' => 'some', 'getType' => $mixedType, 'isOptional' => false]
            ),
            $this->createConfiguredMock(
                ReflectionParameter::class,
                ['getName' => 'good', 'getType' => $stringType, 'isOptional' => true, 'getDefaultValue' => 'ok']
            ),
        ];
        $reflectionFactory = $this->createReflectionFactoryMock($invokable, false, $parameters);

        $genArg = $this->createCallbackMock($this->exactly(3), function (InvocationMocker $invoke) {
            $invoke
                ->with(...Consecutive::create(['id', null, null], ['some', null, null], ['good', 'string', 'ok']))
                ->willReturnOnConsecutiveCalls("\$var['id'] ?? NULL", "\$var['some'] ?? NULL", "\$var['good'] ?? 'ok'");
        });

        $invoker = new Invoker(null, $reflectionFactory);
        $code = $invoker->generateInvocation(compact('controller', 'action'), $genArg);

        $this->assertEquals($expectedCode, $code);
    }

    public function testGenerateOfStaticMethod()
    {
        $parameters = [
            $this->createConfiguredMock(ReflectionParameter::class, ['getName' => 'id', 'isOptional' => false]),
        ];
        $reflectionFactory = $this->createReflectionFactoryMock(['FooController', 'barAction'], true, $parameters);

        $genArg = $this->createCallbackMock($this->once(), ['id', '', null], "\$var['id'] ?? NULL");

        $invoker = new Invoker(null, $reflectionFactory);
        $code = $invoker->generateInvocation(['controller' => 'FooController', 'action' => 'barAction'], $genArg);

        $this->assertEquals("\FooController::barAction(\$var['id'] ?? NULL)", $code);
    }

    #[DataProvider('invokableProvider')]
    public function testGenerateWithNamespace($controller, $action, $invokable, $expected)
    {
        $invokable[0] = 'App\\Generated\\' . $invokable[0];
        $expected = str_replace('new ', 'new \\App\\Generated', $expected);

        $createInvokable = function (?string $controller, ?string $action) {
            return $controller !== null
                ? ['App\\Generated\\' . $controller, $action ?? 'defaultAction']
                : ['App\\Generated\\' . $action, '__invoke'];
        };

        $reflectionFactory = $this->createReflectionFactoryMock($invokable, false, []);
        $genArg = $this->createCallbackMock($this->never());

        $invoker = new Invoker($createInvokable, $reflectionFactory);
        $code = $invoker->generateInvocation(compact('controller', 'action'), $genArg);

        $this->assertEquals(sprintf($expected, ''), $code);
    }

    public function testGenerateWithFunction()
    {
        $parameters = [
            $this->createConfiguredMock(ReflectionParameter::class, ['getName' => 'id', 'isOptional' => false]),
        ];

        $reflection = $this->createMock(ReflectionFunction::class);
        $reflection->expects($this->any())->method('getParameters')->willReturn($parameters);

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->never())->method('reflectMethod');
        $reflectionFactory->expects($this->once())->method('reflectFunction')
            ->with()->willReturn($reflection);

        $genArg = $this->createCallbackMock($this->once(), ['id', '', null], "\$var['id'] ?? NULL");

        $createInvokable = function (?string $controller, ?string $action) {
            return $controller . '_' . str_replace('-', '', $action);
        };

        $invoker = new Invoker($createInvokable, $reflectionFactory);
        $code = $invoker->generateInvocation(['controller' => 'foo', 'action' => 'to-do'], $genArg);

        $this->assertEquals("\\foo_todo(\$var['id'] ?? NULL)", $code);
    }

    public function testGenerateWithMethodString()
    {
        $parameters = [
            $this->createConfiguredMock(ReflectionParameter::class, ['getName' => 'id', 'isOptional' => false]),
        ];
        $reflectionFactory = $this->createReflectionFactoryMock(['App\\Foo', 'toDo'], false, $parameters);

        $genArg = $this->createCallbackMock($this->once(), ['id', '', null], "\$var['id'] ?? NULL");

        $createInvokable = $this->createCallbackMock($this->once(), ['foo', 'to-do'], 'App\\Foo::toDo');

        $invoker = new Invoker($createInvokable, $reflectionFactory);
        $code = $invoker->generateInvocation(['controller' => 'foo', 'action' => 'to-do'], $genArg);

        $this->assertEquals("(new \\App\\Foo)->toDo(\$var['id'] ?? NULL)", $code);
    }

    public static function invalidInvokerProvider()
    {
        $closure = function () {
        };

        return [
            '(int)42' => [42, 'integer'],
            'Closure' => [$closure, 'Closure'],
            "[42, 'hello']" => [[42, 'hello'], '[integer, string]'],
            "[stdClass, 'foo']" => [[new \stdClass(), 'foo'], '[stdClass, string]'],
            "['foo', 'bar', 'qux']" => [['foo', 'bar', 'qux'], '[string, string, string]'],
            "foo::bar::zoo" => ['foo::bar::zoo', 'string'],
            "['foo\\bar\\zoo', 'foo\\roo']" => [['foo\\bar\\zoo', 'foo\\roo'], '[string, string]'],
        ];
    }

    #[DataProvider('invalidInvokerProvider')]
    public function testGenerateWithInvalidInvoker($invoker, $type)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invokable should be a function or array with class name and method, "
            . "{$type} given");

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->never())->method($this->anything());

        $genArg = $this->createCallbackMock($this->never());

        $createInvokable = $this->createCallbackMock($this->once(), ['foo', 'to-do'], $invoker);

        $invoker = new Invoker($createInvokable, $reflectionFactory);

        $invoker->generateInvocation(['controller' => 'foo', 'action' => 'to-do'], $genArg);
    }

    public function testGenerateWithNeitherControllerOrAction()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("Neither controller or action is set");

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->never())->method($this->anything());

        $genArg = $this->createCallbackMock($this->never());

        $invoker = new Invoker(null, $reflectionFactory);

        $invoker->generateInvocation(['controller' => null, 'action' => null], $genArg);
    }

    public function testGenerateDefault()
    {
        $expected = <<<CODE
if (\$allowedMethods === []) {
    http_response_code(404);
    echo "Not Found";
} else {
    http_response_code(405);
    header('Allow: ' . join(', ', \$allowedMethods));
    echo "Method Not Allowed";
}
CODE;

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $invoker = new Invoker(null, $reflectionFactory);

        $this->assertEquals($expected, $invoker->generateDefault());
    }

    #[DataProvider('invokableProvider')]
    public function testCreateInvokable($controller, $action, $expected)
    {
        $invokable = Invoker::createInvokable($controller, $action);

        $this->assertSame($expected, $invokable);
    }
}
