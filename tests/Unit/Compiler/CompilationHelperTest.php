<?php

namespace Lexide\Syringe\Test\Unit\Compiler;

use Lexide\Syringe\Compiler\CompilationHelper;
use PHPUnit\Framework\TestCase;

class CompilationHelperTest extends TestCase
{

    public function testDetectServiceReference()
    {
        $helper = new CompilationHelper();

        $this->assertTrue($helper->isServiceReference("@foo"));
        $this->assertFalse($helper->isServiceReference("foo"));
        $this->assertFalse($helper->isServiceReference("foo @bar"));
    }

    public function testGetServiceKey()
    {
        $helper = new CompilationHelper();
        $this->assertSame("foo", $helper->getServiceKey("@foo"));
    }

    public function testGetServiceReference()
    {
        $helper = new CompilationHelper();
        $this->assertSame("@foo", $helper->getServiceReference("foo"));
        $this->assertSame("@bar", $helper->getServiceReference("@bar"));
    }

    /**
     * @dataProvider findingParametersProvider
     *
     * @param $value
     * @param $expectedParameter
     * @param int $offset
     */
    public function testFindingParameters($value, $expectedParameter, $offset = 0)
    {
        $helper = new CompilationHelper();
        $this->assertSame($expectedParameter, $helper->findNextParameter($value, $offset));
    }

    /**
     * @dataProvider replacingParametersProvider
     *
     * @param $value
     * @param $parameter
     * @param $replacement
     * @param $expected
     * @param bool $removeChars
     */
    public function testReplacingParameters($value, $parameter, $replacement, $expected, $removeChars = false)
    {
        $helper = new CompilationHelper();
        $this->assertSame($expected, $helper->replaceParameterReference($value, $parameter, $replacement, $removeChars));
    }

    /**
     * @dataProvider findingConstantsProvider
     *
     * @param $value
     * @param $expectedParameter
     * @param int $offset
     */
    public function testFindingConstants($value, $expectedParameter, $offset = 0)
    {
        $helper = new CompilationHelper();
        $this->assertSame($expectedParameter, $helper->findNextConstant($value, $offset));
    }

    /**
     * @dataProvider replacingConstantsProvider
     *
     * @param $value
     * @param $parameter
     * @param $replacement
     * @param $expected
     * @param bool $removeChars
     */
    public function testReplacingConstants($value, $parameter, $replacement, $expected, $removeChars = false)
    {
        $helper = new CompilationHelper();
        $this->assertSame($expected, $helper->replaceConstantReference($value, $parameter, $replacement, $removeChars));
    }

    public function findingParametersProvider(): array
    {
        return [
            "no parameter" => [
                "no parameter here",
                null
            ],
            "value is one parameter" => [
                "%test%",
                "test"
            ],
            "value contains one parameter" => [
                "This %is% a test",
                "is"
            ],
            "finds first parameter" => [
                "This %test% has %two% parameters",
                "test"
            ],
            "finds first parameter after offset" => [
                "This %test% has %two% parameters",
                "two",
                11
            ],
            "ignores escaped characters" => [
                "This %% %test% has escaped characters",
                "test"
            ]
        ];
    }

    public function replacingParametersProvider(): array
    {
        return [
            "replace parameter" => [
                "%foo%",
                "foo",
                "bar",
                "%bar%"
            ],
            "replace first instance only" => [
                "%foo% %foo% %foo%",
                "foo",
                "bar",
                "%bar% %foo% %foo%"
            ],
            "replace in middle of string" => [
                "This string has lots of %foo% before and after",
                "foo",
                "bar",
                "This string has lots of %bar% before and after"
            ],
            "can replace regex special characters correctly" => [
                "%%foo bar%% %foo.bar%",
                "foo.bar",
                "baz.bam",
                "%%foo bar%% %baz.bam%"
            ],
            "removes parameters characters too" => [
                "There is no %spoon%.",
                "spoon",
                "",
                "There is no .",
                true
            ]
        ];
    }

    public function findingConstantsProvider(): array
    {
        return [
            "no constant" => [
                "no constant here",
                null
            ],
            "value is one constant" => [
                "^test^",
                "test"
            ],
            "value contains one constant" => [
                "This ^is^ a test",
                "is"
            ],
            "finds first constant" => [
                "This ^test^ has ^two^ constants",
                "test"
            ],
            "finds first constant after offset" => [
                "This ^test^ has ^two^ constants",
                "two",
                11
            ],
            "ignores escaped characters" => [
                "This ^^ ^test^ has escaped characters",
                "test"
            ]
        ];
    }

    public function replacingConstantsProvider(): array
    {
        return [
            "replace constant" => [
                "^foo^",
                "foo",
                "bar",
                "^bar^"
            ],
            "replace first instance only" => [
                "^foo^ ^foo^ ^foo^",
                "foo",
                "bar",
                "^bar^ ^foo^ ^foo^"
            ],
            "replace in middle of string" => [
                "This string has lots of ^foo^ before and after",
                "foo",
                "bar",
                "This string has lots of ^bar^ before and after"
            ],
            "can replace regex special characters correctly" => [
                "^^foo bar^^ ^foo.bar^",
                "foo.bar",
                "baz.bam",
                "^^foo bar^^ ^baz.bam^"
            ],
            "removes constants characters too" => [
                "There is no ^spoon^.",
                "spoon",
                "",
                "There is no .",
                true
            ]
        ];
    }
    
}
