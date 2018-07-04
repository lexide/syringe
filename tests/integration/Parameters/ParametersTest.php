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

    public function testResolvingArrays()
    {
        $test1 = "foo";
        $test2 = "bar";
        $test3 = "baz";
        $test4 = "foz";
        $prefix = "pre-";

        $this->container["test1"] = $test1;
        $this->container["test2"] = $test2;
        $this->container["test3"] = $test3;
        $this->container["test4"] = $test4;
        $this->container["prefix"] = $prefix;

        $list = $this->container["listTest"];
        $this->assertEquals($test1, $list[0]);
        $this->assertEquals($test2, $list[1]);
        $this->assertEquals($test3, $list[2]);
        $this->assertEquals($test4, $list[3]);

        $hash = $this->container["hashTest"];
        $key1 = $prefix.$test1;
        $key2 = $prefix.$test3;
        $this->assertArrayHasKey($key1, $hash);
        $this->assertArrayHasKey($key2, $hash);

        $this->assertEquals($test2, $hash[$key1]);
        $this->assertEquals($test4, $hash[$key2]);


    }
}
