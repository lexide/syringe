<?php

namespace Lexide\Syringe\Test\Integration;

use Lexide\Syringe\Integration\Service\CollectionService;
use Lexide\Syringe\Syringe;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    /**
     * @var \Pimple\Container
     */
    protected $container;

    public function setUp(): void
    {
        $configFiles = [
            "service.json",
            "private_test" => "aliased.json"
        ];

        Syringe::init(__DIR__, $configFiles);
        $this->container = Syringe::createContainer();
    }

    public function testTags()
    {
        /** @var CollectionService $collection */
        $collection = $this->container["tagCollection"];

        $this->assertNotEmpty($collection->services, "No services were injected using the tag #duds");
        $this->assertCount(5, $collection->services, "Tag collection contained an unexpected number of services (" . count($collection->services) . ")");

        $tagNames = array_flip(array_keys($collection->services));

        $this->assertArrayHasKey("testKey", $tagNames, "Name 'testKey' was missing from the tagged service");
        $this->assertArrayHasKey(0, $tagNames, "Index 0 was missing from the tagged service");
        $this->assertArrayHasKey(10, $tagNames, "Index 10 was missing from the tagged service");
        $this->assertArrayHasKey(11, $tagNames, "Index 11 was missing from the tagged service");
        $this->assertArrayHasKey(12, $tagNames, "Index 12 was missing from the tagged service");


        unset($tagNames["testKey"]);
        end($tagNames);
        $unnamedKey = key($tagNames);

        $this->assertNotFalse($unnamedKey, "Unnamed service was not injected");

        $this->assertInstanceOf(
            "\\Lexide\\Syringe\\IntegrationTests\\Service\\DudService",
            $collection->services[$unnamedKey],
            "An incorrect service was injected: " . print_r($collection->services, true)
        );
    }
}
