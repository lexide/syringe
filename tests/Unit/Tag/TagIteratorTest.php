<?php

namespace Lexide\Syringe\Test\Unit\Tag;

use Lexide\Syringe\Exception\ServiceException;
use Lexide\Syringe\Tag\TagCollection;
use Lexide\Syringe\Tag\TagIterator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class TagIteratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container|MockInterface $container;
    protected TagCollection|MockInterface $tagCollection;

    public function setUp(): void
    {
        $this->container = \Mockery::mock(Container::class);
        $this->tagCollection = \Mockery::mock(TagCollection::class);
    }

    public function testLoadingIsLazy()
    {
        $taggedServices = [
            "foo" => [],
            "bar" => [],
            "baz" => []
        ];

        $this->container->shouldNotReceive("offsetGet");

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);

        $iterator->valid();
        $iterator->key();
        $iterator->context();
        $iterator->next();
        isset($iterator["foo"]);
    }

    public function testLoadingByIteration()
    {
        $service = "foo";
        $value = "bar";
        $taggedServices = [
            $service => [],
            "baz" => [],
            "fiz" => []
        ];

        $this->container->shouldReceive("offsetGet")->with($service)->once()->andReturn($value);

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);

        $this->assertSame($value, $iterator->current());
    }

    public function testLoadingByArrayAccessKey()
    {
        $service = "two";
        $key = "bar";
        $value = "baz";
        $taggedServices = [
            "one" => ["key" => "foo"],
            $service => ["key" => $key],
            "three" => ["key" => "fiz"]
        ];

        $this->container->shouldReceive("offsetGet")->with($service)->once()->andReturn($value);

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);

        $this->assertSame($value, $iterator[$key]);
    }

    public function testLoadingByArrayAccessIndex()
    {
        $service = "three";
        $index = 2;
        $value = "fiz";
        $taggedServices = [
            "one" => [],
            "two" => [],
            $service => []
        ];

        $this->container->shouldReceive("offsetGet")->with($service)->once()->andReturn($value);

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);

        $this->assertSame($value, $iterator[$index]);
    }

    #[DataProvider("invalidKeyProvider")]
    public function testArrayAccessInvalidKey(string|int $invalidKey)
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/could not find/i");

        $this->container->shouldNotReceive("offsetGet");
        $this->tagCollection->shouldReceive("getServices")->once()->andReturn([]);

        $iterator = new TagIterator($this->container, $this->tagCollection);
        $iterator[$invalidKey];
    }

    public function testOrderedServices()
    {
        $taggedServices = [
            "foo" => ["order" => 20],
            "bar" => [],
            "baz" => ["order" => 30],
            "fiz" => ["order" => 10]
        ];

        $expectedOrder = [
            "fiz",
            "foo",
            "baz",
            "bar"
        ];

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);
        $this->container->shouldReceive("offsetGet")->andReturnArg(0);

        $iterator = new TagIterator($this->container, $this->tagCollection);
        $keys = [];
        $keysIndex = 0;
        foreach($iterator as $index => $key) {
            // zero indexed, not order indexed
            $this->assertSame($keysIndex, $index);
            $keys[$keysIndex] = $key;
            ++$keysIndex;
        }
        $this->assertSame($expectedOrder, $keys);
    }

    public function testContext()
    {
        $key = "test";
        $custom = "three";

        $taggedServices = [
            "foo" => ["order" => 1, "custom" => "one"],
            "bar" => ["order" => 2, "custom" => "two"],
            "baz" => ["order" => 3, "key" => $key, "custom" => $custom],
            "fiz" => ["order" => 4, "custom" => "four"]
        ];

        $expectedContexts = [
            "one",
            "two",
            $custom,
            "four"
        ];

        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);
        $iterator = new TagIterator($this->container, $this->tagCollection);
        $contexts = [];
        $failSafe = 0;
        while($iterator->valid() && $failSafe < 10) {
            $contexts[] = $iterator->context()["custom"] ?? null;
            $iterator->next();
            ++$failSafe;
        }

        $this->assertSame($expectedContexts, $contexts);

        $this->assertSame("three", $iterator->context($key)["custom"]);

    }

    #[DataProvider("invalidKeyProvider")]
    public function testInvalidContextKey($invalidKey)
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/could not find/i");

        $taggedServices = [
            "one" => ["key" => "foo"],
            "two" => ["key" => "bar"],
            "three" => ["key" => "baz"]
        ];
        $this->container->shouldNotReceive("offsetGet");
        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);
        $iterator->context($invalidKey);
    }

    public function testInvalidContextIteration()
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/no more services/");

        $taggedServices = [
            "one" => [],
            "two" => [],
            "three" => []
        ];
        $this->container->shouldNotReceive("offsetGet");
        $this->tagCollection->shouldReceive("getServices")->once()->andReturn($taggedServices);

        $iterator = new TagIterator($this->container, $this->tagCollection);
        while ($iterator->valid()) {
            $iterator->next();
        }
        $iterator->context();
    }

    public static function invalidKeyProvider(): array
    {
        return [
            "key" => ["doesn't exist"],
            "index" => [3]
        ];
    }

}
