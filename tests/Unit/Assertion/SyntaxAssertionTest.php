<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\SyntaxAssertion;
use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Schema\SchemaLinter;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\SyntaxValidatorFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class SyntaxAssertionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use AssertionTestTrait;

    protected SchemaLinter|MockInterface $linter;
    protected SyntaxValidatorFactory|MockInterface $validatorFactory;
    protected SyntaxValidator|MockInterface $validator;
    protected ErrorHelper|MockInterface $helper;
    protected Container|MockInterface $container;

    protected SyntaxAssertion $assertion;

    public function setUp(): void
    {
        $this->linter = \Mockery::mock(SchemaLinter::class);
        $this->validator = \Mockery::mock(SyntaxValidator::class);
        $this->validatorFactory = \Mockery::mock(SyntaxValidatorFactory::class);
        $this->validatorFactory->shouldReceive("create")->andReturn($this->validator);
        $this->helper = \Mockery::mock(ErrorHelper::class);
        $this->helper->shouldReceive("assertionError")->passthru();
        $this->container = \Mockery::mock(Container::class);
        $this->container->shouldReceive("offsetExists")->andReturnTrue();

        $this->assertion = new SyntaxAssertion($this->linter, $this->validatorFactory, $this->helper);
    }

    public function testInvalidOperand()
    {
        $errors = $this->assertion->assert("blah", "foo", null, null, $this->container);
        $this->checkMessages($errors, ["/invalid schemata/i"]);
    }

    public function testInvalidSchemata()
    {
        $schemata = ["bar" => "baz"];

        $error = new SyringeError("assertion", "foo");
        $this->linter->shouldReceive("lint")->with(["schemata" => ["syringe" => $schemata]])->once()->andReturn([$error, $error, $error]);

        $errors = $this->assertion->assert($schemata, "foo", AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/foo/", "/foo/", "/foo/"]);

    }

    public function testNotParameter()
    {
        $this->linter->shouldReceive("lint")->andReturn([]);
        $errors = $this->assertion->assert(["bar" => "baz"], "foo", AssertionInterface::TYPE_SERVICE, null, $this->container);
        $this->checkMessages($errors, ["/only.*on parameters/"]);
    }

    public function testInvalidParameterValue()
    {
        $param = "foo";
        $this->linter->shouldReceive("lint")->andReturn([]);
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("not an array");

        $errors = $this->assertion->assert(["bar" => "baz"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/not a data structure/"]);
    }

    public function testFailure()
    {
        $param = "foo";
        $data = ["bar" => "baz"];
        $error = new SyringeError("assertion", "foo");

        $this->linter->shouldReceive("lint")->andReturn([]);
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn($data);
        $this->validator->shouldReceive("validate")->with($data, \Mockery::any())->once()->andReturn([$error, $error]);

        $errors = $this->assertion->assert(["fiz" => "fuz"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/foo/", "/foo/"]);

    }

    public function testSuccess()
    {
        $param = "foo";
        $data = ["bar" => "baz"];

        $this->linter->shouldReceive("lint")->andReturn([]);
        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn($data);
        $this->validator->shouldReceive("validate")->with($data, \Mockery::any())->once()->andReturn([]);

        $errors = $this->assertion->assert(["fiz" => "fuz"], $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->assertCount(0, $errors);
    }
}
