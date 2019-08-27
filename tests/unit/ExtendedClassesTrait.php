<?php

namespace Jasny\SwitchRoute\Tests;

use Jasny\ReflectionFactory\ReflectionFactoryInterface;
use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Generator\GenerateFunction;
use Jasny\SwitchRoute\Generator\GenerateInvokeMiddleware;
use Jasny\SwitchRoute\Generator\GenerateRouteMiddleware;
use Jasny\SwitchRoute\Invoker;
use Jasny\SwitchRoute\InvokerInterface;
use ReflectionFunctionAbstract;
use ReflectionMethod;

trait ExtendedClassesTrait
{
    private $extendedGenerator;
    private $extendedGenerateFunction;
    private $extendedGenerateInvokeMiddleware;
    private $extendedGenerateRouteMiddleware;
    private $extendedInvoker;

    private function initExtendedGenerator()
    {
        if (null !== $this->extendedGenerator) return;
        $this->extendedGenerator = new class extends Generator
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
        if (null !== $this->extendedGenerateFunction) return;
        $this->extendedGenerateFunction = new class extends GenerateFunction
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

            public function getInvoker(): InvokerInterface
            {
                return $this->invoker;
            }
        };
    }

    private function initExtendedGenerateInvokeMiddleware()
    {
        if (null !== $this->extendedGenerateInvokeMiddleware) return;
        $this->extendedGenerateInvokeMiddleware = new class extends GenerateInvokeMiddleware
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

            public function callGroupRoutes(array $routes): array
            {
                return $this->groupRoutes($routes);
            }

            public function callGenerateSwitchFromRoutes(array $routes): string
            {
                return $this->generateSwitchFromRoutes($routes);
            }

            public function getInvoker(): InvokerInterface
            {
                return $this->invoker;
            }
        };
    }

    private function initExtendedGenerateRouteMiddleware() {
        if (null !== $this->extendedGenerateRouteMiddleware) return;
        $this->extendedGenerateRouteMiddleware = new class extends GenerateRouteMiddleware {
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

    private function initExtendedInvoker() {
        if (null !== $this->extendedInvoker) return;
        $this->extendedInvoker = new class extends Invoker {
            public function callGenerateInvocationArgs(ReflectionFunctionAbstract $reflection, callable $genArg): string
            {
                return $this->generateInvocationArgs($reflection, $genArg);
            }

            public function callGenerateInvocationMethod(array $invokable, ReflectionMethod $reflection, string $new = '(new \\%s)'): string
            {
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
}
