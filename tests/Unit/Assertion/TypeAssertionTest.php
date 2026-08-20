<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\TypeAssertion;
use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Validation\TypeValidator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class TypeAssertionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use AssertionTestTrait;

    protected TypeValidator|MockInterface $validator;
    protected ErrorHelper|MockInterface $helper;
    protected Container|MockInterface $container;

    protected TypeAssertion $assertion;

    public function setUp(): void
    {
        $this->validator = \Mockery::mock(TypeValidator::class);
        $this->helper = \Mockery::mock(ErrorHelper::class);
        $this->helper->shouldReceive("assertionError")->passthru();
        $this->container = \Mockery::mock(Container::class);

        $this->assertion = new TypeAssertion($this->validator, $this->helper);
    }

    public function testInvalidOperand()
    {
        $errors = $this->assertion->assert("not a type", "blah", null, null, $this->container);
        $this->checkMessages($errors, ["/invalid type/i"]);
    }

    public function testService()
    {
        $errors = $this->assertion->assert("any", "blah", AssertionInterface::TYPE_SERVICE, null, $this->container);
        $this->checkMessages($errors, ["/on a service/"]);
    }

    public function testMissingNotNull()
    {
        $errors = $this->assertion->assert("string", "blah", null, null, $this->container);
        $this->checkMessages($errors, ["/does not exist/"]);
    }

    public function testMissingNullSuccess()
    {
        $errors = $this->assertion->assert("null", "blah", null, null, $this->container);
        $this->assertCount(0, $errors);
    }

    public function testMissingAnySuccess()
    {
        $errors = $this->assertion->assert("any", "blah", null, null, $this->container);
        $this->assertCount(0, $errors);
    }

    public function testParameterFailure()
    {
        $param = "foo";
        $type = "string";
        $value = 123;
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn($value);
        $this->validator->shouldReceive("checkType")->with($type, $value)->once()->andReturnFalse();
        $errors = $this->assertion->assert("string", $param, AssertionInterface::TYPE_PARAMETER, "blah", $this->container);
        $this->checkMessages($errors, ["/type was not/"]);
    }

    public function testParameterSuccess()
    {
        $param = "foo";
        $type = "string";
        $value = 123;
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn($value);
        $this->validator->shouldReceive("checkType")->with($type, $value)->once()->andReturnTrue();
        $errors = $this->assertion->assert("string", $param, AssertionInterface::TYPE_PARAMETER, "blah", $this->container);
        $this->assertCount(0, $errors);
    }

}
