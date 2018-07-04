<?php

namespace Lexide\Syringe\IntegrationTests\Parameters;


use Lexide\Syringe\Syringe;

class ParametersTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Pimple\Container
     */
    protected $container;

    public function setUp()
    {
        $configFiles = [
            "parameters.yml"
        ];

        Syringe::init(__DIR__, $configFiles);
        $this->container = Syringe::createContainer();
    }

    public function testArrayParameters()
    {
        $list = $this->container["listTest"];

        $expected = ["one", "two", "three"];

        foreach ($expected as $i => $expectedValue) {
            $this->assertArrayHasKey($i, $list);
            $this->assertEquals($expectedValue, $list[$i]);
        }

        $hash = $this->container["hashTest"];

        foreach ($expected as $expectedValue) {
            $this->assertEquals($expectedValue, key($hash), "Failed testing key = $expectedValue");
            $this->assertEquals($expectedValue, current($hash), "Failed testing value = $expectedValue");
            next($hash);
        }

    }

    public function testResolvingArrays()
    {
        $test = ["foo", "bar", "baz", "foz"];
        $prefix = "pre-";

        $this->container["test0"] = $test[0];
        $this->container["test1"] = $test[1];
        $this->container["test2"] = $test[2];
        $this->container["test3"] = $test[3];
        $this->container["prefix"] = $prefix;

        $list = $this->container["resolvedListTest"];
        foreach ($test as $i => $expected) {
            $this->assertArrayHasKey($i, $list);
            $this->assertEquals($expected, $list[$i]);
        }

        $hash = $this->container["resolvedHashTest"];
        $prefixedKey = $prefix.$test[2];
        $this->assertArrayHasKey("standard", $hash);
        $this->assertArrayHasKey($test[0], $hash);
        $this->assertArrayNotHasKey("%test0%", $hash);
        $this->assertArrayHasKey($prefixedKey, $hash);
        $this->assertArrayNotHasKey("%prefix%%test2%", $hash);

        $this->assertEquals($test[0], $hash["standard"]);
        $this->assertEquals($test[1], $hash[$test[0]]);
        $this->assertEquals($test[3], $hash[$prefixedKey]);


    }
}
