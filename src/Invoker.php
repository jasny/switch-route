<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute;

use Jasny\ReflectionFactory\ReflectionFactory;
use Jasny\ReflectionFactory\ReflectionFactoryInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\Regex\Regex;

/**
 * Invoke the action or script specified by the route.
 */
class Invoker implements InvokerInterface
{
    /**
     * Callback to turn a controller name and action name into a callable.
     * @var callable
     */
    protected $createInvokable;

    protected ReflectionFactoryInterface|ReflectionFactory $reflection;

    /**
     * Invoker constructor.
     *
     * @param callable|null                   $createInvokable
     * @param ReflectionFactoryInterface|null $reflection
     */
    public function __construct(?callable $createInvokable = null, ?ReflectionFactoryInterface $reflection = null)
    {
        $this->createInvokable = $createInvokable ?? \Closure::fromCallable([__CLASS__, 'createInvokable']);
        $this->reflection = $reflection ?? new ReflectionFactory();
    }

    /**
     * Generate code for a function or method class for the route.
     *
     * The argument template should have two '%s' placeholders. The first is used for the argument name, the second for
     *   the default value.
     *
     * @param array    $route
     * @param callable $genArg  Callback to generate code for arguments.
     * @param string   $new     PHP code to instantiate class.
     * @return string
     * @throws ReflectionException
     */
    public function generateInvocation(array $route, callable $genArg, string $new = '(new \\%s)'): string
    {
        ['controller' => $controller, 'action' => $action] = $route + ['controller' => null, 'action' => null];

        $invokable = ($this->createInvokable)($controller, $action);
        $this->assertInvokable($invokable);

        $reflection = $this->getReflection($invokable);
        $call = $reflection instanceof ReflectionFunction
            ? '\\' . $invokable
            : $this->generateInvocationMethod($invokable, $reflection, $new);

        return $call . '(' . $this->generateInvocationArgs($reflection, $genArg) . ')';
    }

    /**
     * Generate the code for a method call.
     *
     * @param array|string     $invokable
     * @param ReflectionMethod $reflection
     * @param string           $new         PHP code to instantiate class.
     * @return string
     */
    protected function generateInvocationMethod(
        array|string $invokable,
        ReflectionMethod $reflection,
        string $new = '(new \\%s)'
    ): string {
        if (is_string($invokable) && strpos($invokable, '::') !== false) {
            $invokable = explode('::', $invokable) + ['', ''];
        }

        return $invokable[1] === '__invoke' || $invokable[1] === ''
            ? sprintf($new, $invokable[0])
            : ($reflection->isStatic() ? "\\{$invokable[0]}::" : sprintf($new, $invokable[0]) . "->") . $invokable[1];
    }

    /**
     * Generate code for the arguments when calling the action.
     *
     * @param ReflectionFunctionAbstract $reflection
     * @param callable                   $genArg
     * @return string
     * @throws ReflectionException
     */
    protected function generateInvocationArgs(ReflectionFunctionAbstract $reflection, callable $genArg): string
    {
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $default = $param->isOptional() ? $param->getDefaultValue() : null;
            $type = $param->getType() instanceof ReflectionNamedType ? $param->getType()->getName() : null;
            $args[] = $genArg($param->getName(), $type, $default);
        }

        return join(', ', $args);
    }

    /**
     * Assert that invokable is a function name or array with class and method.
     */
    protected function assertInvokable(mixed $invokable): void
    {
        $valid = is_callable($invokable, true) && (
            (
                is_string($invokable) && Regex::match('/^[a-z_]\w*(\\\\\w+)*(::[a-z_]\w*)?$/i', $invokable)->hasMatch()
            ) || (
                is_array($invokable) &&
                is_string($invokable[0]) && Regex::match('/^[a-z_]\w*(\\\\\w+)*$/i', $invokable[0])->hasMatch() &&
                Regex::match('/^[a-z_]\w*$/i', $invokable[1])->hasMatch()
            )
        );

        if ($valid) {
            return;
        }

        if (is_array($invokable)) {
            $types = array_map(static function ($item): string {
                return is_object($item) ? get_class($item) : gettype($item);
            }, $invokable);
            $type = '[' . join(', ', $types) . ']';
        } else {
            $type = is_object($invokable) ? get_class($invokable) : gettype($invokable);
        }

        throw new \LogicException("Invokable should be a function or array with class name and method, {$type} given");
    }

    /**
     * Get reflection of invokable.
     *
     * @throws ReflectionException  if function or method doesn't exist
     */
    protected function getReflection(array|string $invokable): ReflectionFunction|ReflectionMethod
    {
        if (is_string($invokable) && strpos($invokable, '::') !== false) {
            $invokable = explode('::', $invokable);
        }

        return is_array($invokable)
            ? $this->reflection->reflectMethod($invokable[0], $invokable[1])
            : $this->reflection->reflectFunction($invokable);
    }

    /**
     * Generate standard code for when no route matches.
     */
    public function generateDefault(): string
    {
        return <<<CODE
if (\$allowedMethods === []) {
    http_response_code(404);
    echo "Not Found";
} else {
    http_response_code(405);
    header('Allow: ' . join(', ', \$allowedMethods));
    echo "Method Not Allowed";
}
CODE;
    }

    /**
     * Default method to create invokable from controller and action FQCN.
     */
    final public static function createInvokable(?string $controller, ?string $action): array
    {
        if ($controller === null && $action === null) {
            throw new \BadMethodCallException("Neither controller or action is set");
        }

        return $controller !== null
            ? [$controller, ($action ?? 'defaultAction')]
            : [$action, '__invoke'];
    }
}
