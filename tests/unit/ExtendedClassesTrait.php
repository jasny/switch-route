<?php

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\Endpoint;
use Jasny\SwitchRoute\Generator;
use Jasny\SwitchRoute\Generator\GenerateFunction;
use Jasny\SwitchRoute\InvokerInterface;

trait ExtendedClassesTrait
{
    private $extendedGenerator;
    private $extendedGenerateFunction;

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
}
