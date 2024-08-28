<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\Tests;

use Jasny\SwitchRoute\NoInvoker;
use Jasny\SwitchRoute\Tests\Utils\CallbackMockTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoInvoker::class)] class NoInvokerTest extends TestCase
{
    use CallbackMockTrait;

    public function testGenerateInvocation()
    {
        $route = ['controller' => 'to-do', 'action' => 'list', 'foo' => 'bar'];
        $genArg = $this->createCallbackMock($this->once(), [null], '["foo" => $segments[2]]');

        $invoker = new NoInvoker();
        $code = $invoker->generateInvocation($route, $genArg);

        $expected = <<<CODE
[200, array (
  'controller' => 'to-do',
  'action' => 'list',
  'foo' => 'bar',
), ["foo" => \$segments[2]]]
CODE;

        $this->assertEquals($expected, $code);
    }

    public function testGenerateDefault()
    {
        $invoker = new NoInvoker();

        $expected = 'return $allowedMethods === [] ? [404] : [405, $allowedMethods];';
        $this->assertEquals($expected, $invoker->generateDefault());
    }
}
