<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\FormatAssertion;
use Lexide\Syringe\Error\ErrorHelper;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class FormatAssertionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use AssertionTestTrait;

    protected ErrorHelper|MockInterface $helper;
    protected Container|MockInterface $container;

    protected FormatAssertion $assertion;

    public function setUp(): void
    {
        $this->helper = \Mockery::mock(ErrorHelper::class);
        $this->helper->shouldReceive("assertionError")->passthru();
        $this->container = \Mockery::mock(Container::class);

        $this->assertion = new FormatAssertion($this->helper);
    }

    public function testInvalidFormat()
    {
        $errors = $this->assertion->assert(["foo"], "blah", null, null, $this->container);
        $this->checkMessages($errors, ["/invalid regex/i"]);
    }

    public function testInvalidRegexFormat()
    {
        $errors = $this->assertion->assert("foo", "blah", null, null, $this->container);
        $this->checkMessages($errors, ["/invalid regex/i"]);
    }

    public function testNotParameter()
    {
        $errors = $this->assertion->assert("/foo/", "blah", AssertionInterface::TYPE_SERVICE, null, $this->container);
        $this->checkMessages($errors, ["/only.*on parameters/i"]);
    }

    public function testNotAString()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn(["bar" => "baz"]);

        $errors = $this->assertion->assert("/bar/", $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/isn't a string/"]);

    }

    public function testFailure()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("miss");

        $errors = $this->assertion->assert("/hit/", $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->checkMessages($errors, ["/did not match/"]);
    }

    public function testSuccess()
    {
        $param = "foo";

        $this->container->shouldReceive("offsetGet")->with($param)->once()->andReturn("I hit the regex");

        $errors = $this->assertion->assert("/hit/", $param, AssertionInterface::TYPE_PARAMETER, null, $this->container);
        $this->assertCount(0, $errors);
    }
}
