<?php

namespace Lexide\Syringe\Test\Unit\Reference;

use Lexide\Syringe\Reference\ReferenceHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReferenceHelperTest extends TestCase
{

    public function testDetectServiceReference()
    {
        $helper = new ReferenceHelper();

        $this->assertTrue($helper->isServiceReference("@foo"));
        $this->assertFalse($helper->isServiceReference("foo"));
        $this->assertFalse($helper->isServiceReference("foo @bar"));
    }

    public function testGetServiceKey()
    {
        $helper = new ReferenceHelper();
        $this->assertSame("foo", $helper->getServiceKey("@foo"));
    }

    public function testGetServiceReference()
    {
        $helper = new ReferenceHelper();
        $this->assertSame("@foo", $helper->getServiceReference("foo"));
        $this->assertSame("@bar", $helper->getServiceReference("@bar"));
    }

    #[DataProvider("findingParametersProvider")]
    public function testFindingParameters($value, $expectedParameter, $offset = 0)
    {
        $helper = new ReferenceHelper();
        $this->assertSame($expectedParameter, $helper->findNextParameter($value, $offset));
    }

    #[DataProvider("replacingParametersProvider")]
    public function testReplacingParameters($value, $parameter, $replacement, $expected, $removeChars = false)
    {
        $helper = new ReferenceHelper();
        $this->assertSame($expected, $helper->replaceParameterReference($value, $parameter, $replacement, $removeChars));
    }

    #[DataProvider("findingConstantsProvider")]
    public function testFindingConstants($value, $expectedParameter, $offset = 0)
    {
        $helper = new ReferenceHelper();
        $this->assertSame($expectedParameter, $helper->findNextConstant($value, $offset));
    }

    #[DataProvider("replacingConstantsProvider")]
    public function testReplacingConstants($value, $parameter, $replacement, $expected, $removeChars = false)
    {
        $helper = new ReferenceHelper();
        $this->assertSame($expected, $helper->replaceConstantReference($value, $parameter, $replacement, $removeChars));
    }

    public static function findingParametersProvider(): array
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
                "This \\% %test% has escaped characters",
                "test"
            ]
        ];
    }

    public static function replacingParametersProvider(): array
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
                "\\%foo bar\\% %foo.bar%",
                "foo.bar",
                "baz.bam",
                "\\%foo bar\\% %baz.bam%"
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

    public static function findingConstantsProvider(): array
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
                "This \\^ ^test^ has escaped characters",
                "test"
            ]
        ];
    }

    public static function replacingConstantsProvider(): array
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
