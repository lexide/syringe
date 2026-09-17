<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\EnumAssertion;
use Lexide\Syringe\Error\ErrorHelper;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class EnumAssertionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use AssertionTestTrait;

    protected ErrorHelper|MockInterface $helper;
    protected Container|MockInterface $container;

    protected EnumAssertion $assertion;

    public function setUp(): void
    {
        $this->helper = \Mockery::mock(ErrorHelper::class);
        $this->helper->shouldReceive("assertionError")->passthru();
        $this->container = \Mockery::mock(Container::class);

        $this->assertion = new EnumAssertion($this->helper);
    }

    public function testBadOperand()
    {
        $errors = $this->assertion->assert("not an array", "blah", null, null, $this->container);
        $this->checkMessages($errors, ["/must be an array/"]);
    }

    public function testNotParameter()
    {
        $errors = $this->assertion->assert(["blah"], "blah", AssertionInterface::TYPE_SERVICE, null, $this->container);
        $this->checkMessages($errors, ["/only.*on parameters/"]);
    }

    public function testNotScalar()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn(["bar" => "baz"]);

        $errors = $this->assertion->assert(["blah"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/not a scalar/"]);
    }

    public function testFailure()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("miss");

        $errors = $this->assertion->assert(["hit"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/not.*allowed/"]);
    }

    public function testSuccess()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("hit");

        $errors = $this->assertion->assert(["hit"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->assertCount(0, $errors);
    }



}
