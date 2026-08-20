<?php

namespace Lexide\Syringe\Test\Unit\Reference;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Reference\Reference;
use Lexide\Syringe\Reference\ReferenceHelper;
use Lexide\Syringe\Reference\ReferenceResolver;
use Lexide\Syringe\Tag\TagCollection;
use Lexide\Syringe\Tag\TagIterator;
use Lexide\Syringe\Tag\TagIteratorFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class ReferenceResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected TagIteratorFactory|MockInterface $tagIteratorFactory;
    protected TagIterator|MockInterface $tagIterator;
    protected ReferenceHelper|MockInterface $referenceHelper;
    protected Container|MockInterface $container;

    protected ReferenceResolver $resolver;

    public function setUp(): void
    {
        $this->tagIterator = \Mockery::mock(TagIterator::class);
        $this->tagIteratorFactory = \Mockery::mock(TagIteratorFactory::class);
        $this->referenceHelper = \Mockery::mock(ReferenceHelper::class);
        $this->container = \Mockery::mock(Container::class);

        $this->resolver = new ReferenceResolver($this->referenceHelper, $this->tagIteratorFactory);
    }

    public function testResolvingServices()
    {
        $name = "foo";
        $serviceName = Reference::SERVICE_CHAR . $name;
        $value = "bar";

        $this->referenceHelper->shouldReceive("isServiceReference")->with($serviceName)->once()->andReturnTrue();
        $this->referenceHelper->shouldReceive("getServiceKey")->with($serviceName)->once()->andReturn($name);

        $this->container->shouldReceive("offsetExists")->with($name)->once()->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->with($name)->once()->andReturn($value);

        $this->assertSame($value, $this->resolver->resolveService($serviceName, $this->container));
    }

    public function testResolvingPrivateServices()
    {
        $name = "foo";
        $serviceName = Reference::SERVICE_CHAR . $name;
        $privateName = "bar";
        $value = "baz";

        $this->referenceHelper->shouldReceive("isServiceReference")->with($serviceName)->once()->andReturnTrue();
        $this->referenceHelper->shouldReceive("getServiceKey")->with($serviceName)->once()->andReturn($name);

        $this->container->shouldReceive("offsetExists")->with($name)->once()->andReturnFalse();
        $this->container->shouldReceive("offsetExists")->with($privateName)->once()->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->with($privateName)->once()->andReturn($value);

        $this->resolver->registerPrivateService($privateName, $name);
        $this->assertSame($value, $this->resolver->resolveService($serviceName, $this->container));
    }

    public function testServiceMissing()
    {

        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessageMatches("/doesn't exist/");

        $name = "foo";
        $serviceName = Reference::SERVICE_CHAR . $name;

        $this->referenceHelper->shouldReceive("isServiceReference")->with($serviceName)->once()->andReturnTrue();
        $this->referenceHelper->shouldReceive("getServiceKey")->with($serviceName)->once()->andReturn($name);

        $this->container->shouldReceive("offsetExists")->with($name)->once()->andReturnFalse();
        $this->container->shouldNotReceive("offsetGet");

        $this->resolver->resolveService($serviceName, $this->container);
    }

    #[DataProvider("tagProvider")]
    public function testResolvingTags(
        bool $tagExists,
        int $taggedServiceCount,
        bool $expectArray,
        string $class = "",
        string $method = "",
        string|int $argumentKey = 0
    ) {
        $tag = "#foo";

        $this->referenceHelper->shouldReceive("isTagReference")->with($tag)->once()->andReturnTrue();

        $collection = $tagExists ? \Mockery::mock(TagCollection::class) : null;

        $taggedService = \Mockery::mock(Reference::class);

        $taggedServices = array_fill(0, $taggedServiceCount, $taggedService);

        $this->container->shouldReceive("offsetExists")->with($tag)->once()->andReturn($tagExists);
        $this->container->shouldReceive("offsetGet")->with($tag)->times($tagExists ? 1 : 0)->andReturn($collection);

        $this->tagIteratorFactory->shouldReceive("create")
            ->with($this->container, $collection)
            ->once()
            ->andReturn($this->tagIterator);
        $this->tagIterator->shouldReceive("valid")->andReturnValues(
            array_merge(
                array_fill(0, $taggedServiceCount, true),
                [false]
            )
        );
        $this->tagIterator->shouldReceive("key")->andReturnValues(array_keys($taggedServices));
        $this->tagIterator->shouldReceive("current")->andReturnValues(array_values($taggedServices));
        $this->tagIterator->shouldReceive("rewind");
        $this->tagIterator->shouldReceive("next");

        $actual = $this->resolver->resolveTag($tag, $this->container, $class, $method, $argumentKey);

        if ($expectArray) {
            $this->assertIsArray($actual);
            $this->assertCount($taggedServiceCount, $actual);
            foreach ($actual as $service) {
                $this->assertSame($taggedService, $service);
            }
        } else {
            $this->assertSame($this->tagIterator, $actual);
        }

    }

    public function testInvalidTagCollectionError()
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessageMatches("/collection.*was invalid/");

        $tag = "#foo";

        $this->referenceHelper->shouldReceive("isTagReference")->with($tag)->once()->andReturnTrue();

        $this->container->shouldReceive("offsetExists")->with($tag)->once()->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->with($tag)->once()->andReturn($this->tagIterator);

        $this->resolver->resolveTag($tag, $this->container);
    }

    #[DataProvider("tagErrorProvider")]
    public function testTagResolutionTypeErrors(
        string $errorRegex,
        string $class,
        string $method,
        string|int $argumentKey = 0
    ) {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessageMatches($errorRegex);

        $tag = "#foo";

        $this->referenceHelper->shouldReceive("isTagReference")->with($tag)->once()->andReturnTrue();

        $collection = \Mockery::mock(TagCollection::class);

        $this->container->shouldReceive("offsetExists")->with($tag)->once()->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->with($tag)->once()->andReturn($collection);

        $this->tagIteratorFactory->shouldReceive("create")
            ->with($this->container, $collection)
            ->once()
            ->andReturn($this->tagIterator);

        $this->resolver->resolveTag($tag, $this->container, $class, $method, $argumentKey);
    }

    #[DataProvider("parameterProvider")]
    public function testResolvingParameters(array $parameters, mixed $parameter, mixed $expectedValue)
    {
        $this->referenceHelper->shouldReceive("findNextParameter")->passthru();
        $this->referenceHelper->shouldReceive("replaceParameterReference")->passthru();
        $this->referenceHelper->shouldReceive("unescapeCharacters")->andReturnArg(0);

        $this->parameterResolutionTest($parameters, $parameter, $expectedValue);
    }

    protected function parameterResolutionTest(array $parameters, mixed $parameter, mixed $expectedValue): void
    {
        $this->referenceHelper->shouldReceive("findNextConstant")->andReturnNull();

        $this->container->shouldReceive("offsetExists")->andReturnTrue();
        $this->container->shouldReceive("offsetGet")->andReturnUsing(fn($key) => $parameters[$key]);

        $this->assertSame($expectedValue, $this->resolver->resolveParameter($parameter, $this->container));
    }

    #[DataProvider("stringReplacementProvider")]
    public function testStringReplacingParameters(array $parameters, string $parameter, string $expectedValue)
    {
        $this->testResolvingParameters($parameters, $parameter, $expectedValue);
    }

    #[DataProvider("escapedCharacterProvider")]
    public function testEscapingCharacters(array $parameters, string $parameter, string $expectedValue)
    {
        $this->referenceHelper->shouldReceive("findNextParameter")->passthru();
        $this->referenceHelper->shouldReceive("replaceParameterReference")->passthru();
        $this->referenceHelper->shouldReceive("findNextConstant")->passthru();
        $this->referenceHelper->shouldReceive("replaceConstantReference")->passthru();
        $this->referenceHelper->shouldReceive("unescapeCharacters")->passthru();

        $this->parameterResolutionTest($parameters, $parameter, $expectedValue);
    }

    #[DataProvider("parameterErrorProvider")]
    public function testParameterResolutionErrors(array $parameters, string $parameter, string $expectedMessageRegex)
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessageMatches($expectedMessageRegex);

        $this->referenceHelper->shouldReceive("findNextParameter")->passthru();
        $this->referenceHelper->shouldReceive("replaceParameterReference")->passthru();
        $this->referenceHelper->shouldReceive("unescapeCharacters")->andReturnArg(0);
        $this->referenceHelper->shouldReceive("findNextConstant")->andReturnNull();

        $this->container->shouldReceive("offsetExists")->andReturnUsing(fn($key) => isset($parameters[$key]));
        $this->container->shouldReceive("offsetGet")->andReturnUsing(fn($key) => $parameters[$key]);

        $this->resolver->resolveParameter("%$parameter%", $this->container);
    }

    #[DataProvider("constantProvider")]
    public function testResolvingConstants(string $string, mixed $expectedValue)
    {
        $this->referenceHelper->shouldReceive("findNextParameter")->andReturnNull();
        $this->referenceHelper->shouldReceive("unescapeCharacters")->andReturnArg(0);

        $this->referenceHelper->shouldReceive("findNextConstant")->passthru();
        $this->referenceHelper->shouldReceive("replaceConstantReference")->passthru();

        $this->assertSame($expectedValue, $this->resolver->resolveParameter($string, $this->container));
    }

    #[DataProvider("invalidConstantProvider")]
    public function testConstantResolutionErrors(string $constantReference, string $errorPattern)
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessageMatches($errorPattern);

        $this->referenceHelper->shouldReceive("findNextParameter")->andReturnNull();
        $this->referenceHelper->shouldReceive("unescapeCharacters")->andReturnArg(0);

        $this->referenceHelper->shouldReceive("findNextConstant")->passthru();
        $this->referenceHelper->shouldReceive("replaceConstantReference")->passthru();

        $reference = "^$constantReference^";

        $this->resolver->resolveParameter($reference, $this->container);
    }

    public static function tagProvider(): array
    {
        $mockClass = ReferenceTestMock::class;

        return [
            "empty tag" => [
                false,
                0,
                true
            ],
            "single service" => [
                true,
                1,
                true
            ],
            "multiple services" => [
                true,
                5,
                true
            ],
            "untyped method argument" => [
                true,
                2,
                true,
                $mockClass,
                "usingUntyped"
            ],
            "mixed method argument" => [
                true,
                2,
                true,
                $mockClass,
                "usingMixed"
            ],
            "array method argument" => [
                true,
                2,
                true,
                $mockClass,
                "usingArray"
            ],
            "iterable method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingIterable"
            ],
            "iterator method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingIterator"
            ],
            "traversable method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingTraversable"
            ],
            "array access method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingArrayAccess"
            ],
            "tagIterator method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingTagIterator"
            ],
            "positional method argument" => [
                true,
                2,
                true,
                $mockClass,
                "usingMultipleArgs",
                2
            ],
            "named method argument" => [
                true,
                2,
                true,
                $mockClass,
                "usingMultipleArgs",
                "baz"
            ],
            "union typed method argument" => [
                true,
                2,
                false,
                $mockClass,
                "usingUnionType"
            ]
        ];
    }

    public static function tagErrorProvider(): array
    {
        $mockClass = ReferenceTestMock::class;

        return [
            "method argument doesn't exist (positional)" => [
                "/does not have argument/",
                $mockClass,
                "usingArray",
                1
            ],
            "method argument doesn't exist (named)" => [
                "/does not have argument/",
                $mockClass,
                "usingArray",
                "list"
            ],
            "method doesn't exist" => [
                "/reflection error/",
                $mockClass,
                "notAMethod"
            ],
            "class doesn't exist" => [
                "/reflection error/",
                "Foo\\Bar\\Baz",
                "unknown"
            ],
            "invalid method argument type (single type)" => [
                "/type.*argument.*does not allow/",
                $mockClass,
                "usingMultipleArgs",
                1
            ],
            "invalid method argument type (union type)" => [
                "/type.*argument.*does not allow/",
                $mockClass,
                "usingUnionType",
                1
            ],
        ];
    }

    public static function parameterProvider(): array
    {
        return [
            "string" => [
                [],
                "blah",
                "blah"
            ],
            "number" => [
                [],
                123,
                123
            ],
            "bool" => [
                [],
                false,
                false
            ],
            "null" => [
                [],
                null,
                null
            ],
            "list" => [
                [
                    "foo" => "one",
                    "bar" => "two",
                    "baz" => "three",
                ],
                ["%foo%", "%bar%", "%baz%"],
                [
                    "one",
                    "two",
                    "three"
                ]
            ],
            "object" => [
                [
                    "foo" => "one",
                    "bar" => "four"
                ],
                [
                    "%foo%" => "two",
                    "three" => "%bar%",
                ],
                [
                    "one" => "two",
                    "three" => "four"
                ]
            ],
            "nested object" => [
                [
                    "foo" => "one",
                    "bar" => "five",
                    "baz" => "eight"
                ],
                [
                    "%foo%" => "two",
                    "three" => [
                        "four" => [
                            "%bar%" => "six",
                            "seven" => "%baz%"
                        ]
                    ]
                ],
                [
                    "one" => "two",
                    "three" => [
                        "four" => [
                            "five" => "six",
                            "seven" => "eight"
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function stringReplacementProvider(): array
    {
        $value = "find me!";

        return [
            "simple value replacement" => [
                [
                    "foo" => "%bar%",
                    "bar" => $value
                ],
                "%foo%",
                $value
            ],
            "chained value replacement" => [
                [
                    "foo" => "%bar%",
                    "bar" => "%baz%",
                    "baz" => "%fiz%",
                    "fiz" => $value
                ],
                "%foo%",
                $value
            ],
            "string substitution" => [
                [
                    "foo" => "one %bar% three",
                    "bar" => "two"
                ],
                "%foo%",
                "one two three"
            ],
            "multiple substitutions" => [
                [
                    "foo" => "%bar% %baz% %fiz%",
                    "bar" => "three",
                    "baz" => "two",
                    "fiz" => "one",
                ],
                "%foo%",
                "three two one"
            ],
            "repetitive substitution" => [
                [
                    "foo" => "More %bar% %bar% %bar% %bar%",
                    "bar" => "and more"
                ],
                "%foo%",
                "More and more and more and more and more"
            ],
            "chained substitutions" => [
                [
                    "quote" => "%one% %two% %three% %four% %one%",
                    "one" => "%five% will alone I set my mind in motion.",
                    "two" => "%five% the juice of Sapho that thoughts %six% speed.",
                    "three" => "The lips %six% %seven%.",
                    "four" => "The %seven% become a warning.",
                    "five" => "It is by",
                    "six" => "acquire",
                    "seven" => "stains"
                ],
                "%quote%",
                "It is by will alone I set my mind in motion. It is by the juice of Sapho that thoughts acquire speed. " .
                    "The lips acquire stains. The stains become a warning. It is by will alone I set my mind in motion."
            ],
            "ignoring mismatched parameter characters" => [
                [
                    "foo" => "one %bar% 3% four",
                    "bar" => "two"
                ],
                "%foo%",
                "one two 3% four"
            ]
        ];
    }

    public static function escapedCharacterProvider(): array
    {
        return [
            "single escape" => [
                [],
                "I'm 80\\% done",
                "I'm 80% done"
            ],
            "multiple escapes" => [
                [],
                "20\\% of 50\\% is 10\\% of the total",
                "20% of 50% is 10% of the total"
            ],
            "escapes and substitution" => [
                [
                    "foo" => "I'm",
                    "bar" => "done"
                ],
                "%foo% 90\\% %bar%",
                "I'm 90% done"
            ],
            "multiple escapes and substitution" => [
                [
                    "foo" => "the total"
                ],
                "20\\% of 50\\% is 10\\% of %foo%",
                "20% of 50% is 10% of the total"
            ],
            "escapes inside substitutions" => [
                [
                    "foo" => "20\\% of %bar% is %baz%",
                    "bar" => "50\\%",
                    "baz" => "10\\% of %fiz%",
                    "fiz" => "the total \\%"
                ],
                "%foo%",
                "20% of 50% is 10% of the total %"
            ],
            "don't escape" => [
                [
                    "foo" => "in front"
                ],
                "backslash \\\\%foo% of a substitution",
                "backslash \\\\in front of a substitution"
            ],
            "escaping the escaped escaper" => [
                [],
                "Um... \\\\\\\\\\\\\\% what is this?",      // 12 "\" here becomes 3 "\" in the final string
                "Um... \\\\\\\\\\\\% what is this?"
            ],
            "escape constant characters" => [
                [],
                "Jim \\^ played Ace Ventura",
                "Jim ^ played Ace Ventura"
            ],
            "escape multiple constant characters" => [
                [],
                "a\\^2 + b\\^2 = c\\^2",
                "a^2 + b^2 = c^2"
            ],
            "escapes and substitutions" => [
                [],
                "\\^^" . Reference::class . "::SERVICE_CHAR^\\^",
                "^@^"
            ],
            "don't escape constants" => [
                [],
                "The service character is \\\\^" . Reference::class . "::SERVICE_CHAR^",
                "The service character is \\\\@"
            ]
        ];
    }

    public static function parameterErrorProvider(): array
    {
        $limit = 105;
        $recursionParameters = [];
        for ($i = 0; $i < $limit; ++$i) {
            $recursionParameters["foo$i"] = "%foo" . ($i + 1) . "%";
        }
        $recursionParameters["foo$limit"] = "Done!";

        return [
            "parameter doesn't exist" => [
                [
                    "foo" => "Parameter %bar% doesn't exist"
                ],
                "foo",
                "/parameter.*doesn't exist/"
            ],
            "value circular reference" => [
                [
                    "foo" => "%bar%",
                    "bar" => "%baz%",
                    "baz" => "%fiz%",
                    "fiz" => "%bar%",
                ],
                "foo",
                "/circular reference/i"
            ],
            "replacement circular reference" => [
                [
                    "foo" => "one %bar%",
                    "bar" => "two three four %baz%",
                    "baz" => "six seven eight %foo%"
                ],
                "foo",
                "/circular reference/i"
            ],
            "max recursion limit" => [
                $recursionParameters,
                "foo0",
                "/recursion limit.*exceeded/"
            ],
            "replacement isn't scalar" => [
                [
                    "foo" => "one %bar% three",
                    "bar" => ["two"]
                ],
                "foo",
                "/not a scalar value/"
            ]
        ];
    }

    public static function constantProvider(): array
    {
        return [
            "root level constant" => [
                "^PHP_INT_MAX^",
                PHP_INT_MAX
            ],
            "class constant" => [
                "^" . Reference::class . "::SERVICE_CHAR^",
                Reference::SERVICE_CHAR
            ],
            "interface constant" => [
                "^" . AssertionInterface::class . "::TYPE_SERVICE^",
                AssertionInterface::TYPE_SERVICE
            ],
            "single string replacement" => [
                "Use ^" . Reference::class . "::SERVICE_CHAR^ for services",
                "Use @ for services"
            ],
            "multiple string replacements" => [
                "The reference characters are ^" .
                    Reference::class . "::SERVICE_CHAR^, ^" .
                    Reference::class . "::PARAMETER_CHAR^ and ^" .
                    Reference::class . "::TAG_CHAR^",
                "The reference characters are @, % and #"
            ]
        ];
    }

    public static function invalidConstantProvider(): array
    {
        $constantMissing = "/constant.*doesn't exist/";
        $testClass = ReferenceTestMock::class;

        return [
            "missing root constant" => [
                "DOES_NOT_EXIST",
                $constantMissing
            ],
            "missing class constant" => [
                "$testClass::MISSING",
                $constantMissing
            ],
            "inaccessible class constant" => [
                "$testClass::MY_CONST",
                $constantMissing
            ],
            "missing class" => [
                "Does\\Not\\Exist::AT_ALL",
                "/class.*doesn't exist/"
            ]
        ];
    }

}
