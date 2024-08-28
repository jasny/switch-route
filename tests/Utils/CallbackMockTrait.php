<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests\Utils;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;

/**
 * Trait to create a mock for a callback.
 */
trait CallbackMockTrait
{
    /**
     * Returns a builder object to create mock objects using a fluent interface.
     *
     * @param string $className
     * @return MockBuilder
     */
    abstract public function getMockBuilder(string $className): MockBuilder;

    /**
     * Create mock for next callback.
     *
     * <code>
     *   $callback = $this->createCallbackMock($this->once(), ['abc'], 10);
     * </code>
     *
     * OR
     *
     * <code>
     *   $callback = $this->createCallbackMock(
     *     $this->once(),
     *     function(InvocationMocker $invoke) {
     *       $invoke->with('abc')->willReturn(10);
     *     }
     *   );
     * </code>
     *
     * @param InvocationOrder     $matcher
     * @param \Closure|array|null $assert
     * @param mixed               $return
     * @return MockObject|callable
     */
    protected function createCallbackMock($matcher, $assert = null, $return = null): MockObject
    {
        if (isset($assert) && !is_array($assert) && !$assert instanceof \Closure) {
            $type = (is_object($assert) ? get_class($assert) . ' ' : '') . gettype($assert);
            throw new \InvalidArgumentException("Expected an array or Closure, got a $type");
        }

        $callback = $this->getMockBuilder(DummyCallback::class)
            ->setMockClassName('CallbackMock_' . \substr(\md5((string) \mt_rand()), 0, 8))
            ->getMock();
        $invoke = $callback->expects($matcher)->method('__invoke');

        if ($assert instanceof \Closure) {
            $assert($invoke);
        } elseif (is_array($assert)) {
            $invoke->with(...$assert)->willReturn($return);
        }

        return $callback;
    }
}

/**
 * @internal
 * @codeCoverageIgnore
 */
class DummyCallback
{
    /**
     * Invoke object.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return null;
    }
}
