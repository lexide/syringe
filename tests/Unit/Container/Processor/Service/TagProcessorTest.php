<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor\Service;

use Lexide\Syringe\Container\Processor\Service\TagProcessor;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Reference\Reference;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Lexide\Syringe\Tag\TagCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class TagProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container|MockInterface $container;
    protected ReferenceResolverInterface $resolver;

    public function setUp(): void
    {
        $this->container = \Mockery::mock(Container::class);
        $this->resolver = \Mockery::mock(ReferenceResolverInterface::class);
    }

    public function testTagCollectionCreation()
    {
        $service = "foo";
        $tagName = "bar";
        $tagKey = Reference::TAG_CHAR . $tagName;
        $definition = [
            "tags" => [
                [
                    "tag" => $tagName
                ]
            ]
        ];
        $this->container->shouldReceive("offsetExists")->with($tagKey)->andReturnFalse();
        $this->container->shouldReceive("offsetSet")
            ->with($tagKey, \Mockery::any())
            ->once()
            ->andReturnUsing(function ($key, $callable) {
                $this->assertInstanceOf(TagCollection::class, $callable());
            });

        $tagCollection = \Mockery::mock(TagCollection::class);
        $this->container->shouldReceive("offsetGet")->with($tagKey)->once()->andReturn($tagCollection);

        $this->resolver->shouldReceive("resolveParameter")->andReturnArg(0);

        $tagCollection->shouldReceive("addService")->with($service, \Mockery::type("array"))->once();

        $processor = new TagProcessor($this->resolver);
        $processor->process($service, $definition, $this->container);

    }

    #[DataProvider("tagProvider")]
    public function testTaggingServices(array $definition, array $expectedTags)
    {
        $service = "foo";

        $this->container->shouldReceive("offsetExists")->andReturnTrue();

        foreach ($expectedTags as $tag => $context) {
            $collection = \Mockery::mock(TagCollection::class);

            $collection->shouldReceive("addService")->with($service, $context)->once();

            $tagKey = Reference::TAG_CHAR . $tag;

            $this->container->shouldReceive("offsetGet")->with($tagKey)->andReturn($collection);
        }

        $this->resolver->shouldReceive("resolveParameter")->andReturnArg(0);

        $processor = new TagProcessor($this->resolver);
        $processor->process($service, $definition, $this->container);
    }

    public function testInvalidTagCollection()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/not a TagCollection/");

        $tag = "foo";
        $tagKey = Reference::TAG_CHAR . $tag;

        $definition = [
            "tags" => [
                ["tag" => $tag]
            ]
        ];

        $this->container->shouldReceive("offsetExists")->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->with($tagKey)->once()->andReturn($this->resolver);

        $processor = new TagProcessor($this->resolver);
        $processor->process("blah", $definition, $this->container);
    }

    public static function tagProvider(): array
    {
        return [
            "single simple tag" => [
                [
                    "tags" => [
                        ["tag" => "one"]
                    ]
                ],
                [
                    "one" => []
                ]
            ],
            "single tag with order" => [
                [
                    "tags" => [
                        ["tag" => "one", "order" => 100]
                    ]
                ],
                [
                    "one" => ["order" => 100]
                ]
            ],
            "single tag with key" => [
                [
                    "tags" => [
                        ["tag" => "one", "key" => "bar"]
                    ]
                ],
                [
                    "one" => ["key" => "bar"]
                ]
            ],
            "single tag with order and key" => [
                [
                    "tags" => [
                        ["tag" => "one", "order" => 100, "key" => "bar"]
                    ]
                ],
                [
                    "one" => ["order" => 100, "key" => "bar"]
                ]
            ],
            "single tag with custom context" => [
                [
                    "tags" => [
                        ["tag" => "one", "context" => ["bar" => "baz"]]
                    ]
                ],
                [
                    "one" => ["bar" => "baz"]
                ]
            ],
            "single tag with everything" => [
                [
                    "tags" => [
                        ["tag" => "one", "order" => 100, "key" => "bar", "context" => ["baz" => "fiz"]]
                    ]
                ],
                [
                    "one" => ["baz" => "fiz", "order" => 100, "key" => "bar"]
                ]
            ],
            "single tag, order overwrites context" => [
                [
                    "tags" => [
                        ["tag" => "one", "order" => 100, "context" => ["order" => 200, "bar" => "baz"]]
                    ]
                ],
                [
                    "one" => ["bar" => "baz", "order" => 100]
                ]
            ],
            "single tag, key overwrites context" => [
                [
                    "tags" => [
                        ["tag" => "one", "key" => "bar", "context" => ["key" => "blah", "baz" => "fiz"]]
                    ]
                ],
                [
                    "one" => ["baz" => "fiz", "key" => "bar"]
                ]
            ],
            "multiple tags" => [
                [
                    "tags" => [
                        ["tag" => "one"],
                        ["tag" => "two"],
                        ["tag" => "three"],
                    ]
                ],
                [
                    "one" => [],
                    "two" => [],
                    "three" => [],
                ]
            ],
            "multiple tags with various context" => [
                [
                    "tags" => [
                        ["tag" => "one", "order" => 100],
                        ["tag" => "two", "key" => "bar"],
                        ["tag" => "three", "key" => "baz", "context" => ["fiz" => 123]],
                    ]
                ],
                [
                    "one" => ["order" => 100],
                    "two" => ["key" => "bar"],
                    "three" => ["fiz" => 123, "key" => "baz"],
                ]
            ]
        ];
    }

}
