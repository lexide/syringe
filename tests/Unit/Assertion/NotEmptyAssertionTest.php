<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\NotEmptyAssertion;
use Lexide\Syringe\Error\ErrorHelper;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class NotEmptyAssertionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use AssertionTestTrait;

    protected ErrorHelper|MockInterface $helper;
    protected Container|MockInterface $container;

    protected NotEmptyAssertion $assertion;

    public function setUp(): void
    {
        $this->helper = \Mockery::mock(ErrorHelper::class);
        $this->helper->shouldReceive("assertionError")->passthru();
        $this->container = \Mockery::mock(Container::class);
        $this->container->shouldReceive("offsetExists")->andReturnTrue();

        $this->assertion = new NotEmptyAssertion($this->helper);
    }

    public function testEmptyParameterDefinition()
    {
        $errors = $this->assertion->assert(null, "foo", AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/defined as.*empty/"]);
    }

    public function testEmptyResolvedParameter()
    {
        $param = "foo";
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturnFalse();

        $errors = $this->assertion->assert(null, $param, AssertionInterface::TYPE_PARAMETER, "%param%", $this->container);
        $this->checkMessages($errors, ["/resolves to.*empty/"]);
    }

    public function testStubService()
    {
        $errors = $this->assertion->assert(null, "foo", AssertionInterface::TYPE_SERVICE, ["stub" => true], $this->container);
        $this->checkMessages($errors, ["/is a stub/"]);

    }

    public function testMissing()
    {
        $errors = $this->assertion->assert(null, "foo", null, null, $this->container);
        $this->checkMessages($errors, ["/is not defined/"]);
    }

    public function testParameterSuccess()
    {
        $param = "foo";
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("bar");

        $errors = $this->assertion->assert(null, $param, AssertionInterface::TYPE_PARAMETER, "%param%", $this->container);
        $this->assertCount(0, $errors);
    }

    public function testServiceSuccess()
    {
        $errors = $this->assertion->assert(null, "foo", AssertionInterface::TYPE_SERVICE, ["stub" => false], $this->container);
        $this->assertCount(0, $errors);
    }
}
