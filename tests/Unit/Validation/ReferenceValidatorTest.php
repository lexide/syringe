<?php

namespace Lexide\Syringe\Test\Unit\Validation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Validation\ReferenceValidator;
use Lexide\Syringe\Validation\ReferenceValidatorHelper;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ReferenceValidatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var ReferenceValidatorHelper|MockInterface
     */
    protected $referenceHelper;

    /**
     * @var CompilationHelper|MockInterface
     */
    protected $compilationHelper;

    /**
     * @var ValidationError|MockInterface
     */
    protected $error;

    public function setUp(): void
    {
        $this->referenceHelper = \Mockery::mock(ReferenceValidatorHelper::class);
        $this->referenceHelper->shouldReceive("setDefinitions");
        $this->referenceHelper->shouldReceive("getServiceKey")->andReturnUsing(function ($service) {
            return ltrim($service, ContainerBuilder::SERVICE_CHAR);
        });

        $this->compilationHelper = \Mockery::mock(CompilationHelper::class);

        $this->error = \Mockery::mock(ValidationError::class);
    }

    public function testParameterValidation()
    {
        $definitions = [
            "parameters" => [
                "one" => "normal value",
                "two" => "%missing%",
                "three" => "%exists%",
                "four" => "%multiple% %params%",
                "five" => "%multiple% %missing% %params%",
                "six" => "%mixed% %params%"
            ]
        ];

        $valueMap = [
            "normal value" => [0, []],
            "%missing%" => [1, []],
            "%exists%" => [0, ["exists" => []]],
            "%multiple% %params%" => [0, ["multiple" => [], "params" => []]],
            "%multiple% %missing% %params%" => [3, []],
            "%mixed% %params%" => [1, ["params" => []]]
        ];

        $this->referenceHelper->shouldReceive("checkValueForReferences")->andReturnUsing(
            function ($value, $options) use ($valueMap) {
                $this->assertArrayHasKey("skipServices", $options);
                $this->assertArrayHasKey($value, $valueMap);
                return [
                    array_fill(0, $valueMap[$value][0], $this->error),
                    $valueMap[$value][1]
                ];

            }
        );

        $referenceMap = [
            "one" => [],
            "two" => [],
            "three" => ["exists"],
            "four" => ["multiple", "params"],
            "five" => [],
            "six" => ["params"],
        ];

        $this->referenceHelper->shouldReceive("addReference")->andReturnUsing(
            function ($parameterReferences, $parameter, $references) use ($referenceMap) {
                $this->assertArrayHasKey($parameter, $referenceMap);
                $this->assertCount(count($referenceMap[$parameter]), $references);
                foreach ($referenceMap[$parameter] as $paramName) {
                    $this->assertArrayHasKey($paramName, $references);
                }
                return []; // don't need to handle circular references in this test
            }
        );

        $expectedErrorCount = array_reduce($valueMap, function ($total, $result) {
            return $total + $result[0];
        }, 0);

        $this->error->shouldReceive("addContext")->with("parameter", \Mockery::any())->times($expectedErrorCount);

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $errors = $validator->validate($definitions);
        $this->assertCount($expectedErrorCount, $errors);
    }

    public function testParametersCircularReferences()
    {
        $definitions = [
            "parameters" => [
                "one" => "blah" // only need one param as a stub
            ]
        ];

        $parameterReferences = [
            "one" => ["fine"],
            "two" => ["error"],
            "three" => ["fine"],
            "four" => ["fine"],
            "five" => ["error"]
        ];

        $parameterErrorCount = 3;
        $circularReferenceErrorCount = count(array_filter($parameterReferences, function ($value) {
            return $value[0] == "error";
        }));

        $this->referenceHelper->shouldReceive("checkValueForReferences")->andReturn([
            array_fill(0, $parameterErrorCount, $this->error),
            $parameterReferences
        ]);
        $this->referenceHelper->shouldReceive("addReference")->andReturnArg(2);

        $this->referenceHelper->shouldReceive("findCircularReferences")->andReturnUsing(
            function ($parameter) use ($parameterReferences) {
                $this->assertArrayHasKey($parameter, $parameterReferences);
                return $parameterReferences[$parameter][0] == "error";
            }
        );
        $this->compilationHelper
            ->shouldReceive("referenceError")
            ->times($circularReferenceErrorCount)
            ->andReturn($this->error);


        $totalErrorCount = $parameterErrorCount + $circularReferenceErrorCount;

        $this->error->shouldReceive("addContext")->with("parameter", \Mockery::any())->times($totalErrorCount);

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $this->assertCount($totalErrorCount, $validator->validate($definitions));
    }

    /**
     * @dataProvider invalidServicesProvider
     *
     * @param array $definitions
     * @param int $expectedErrorCount
     * @param string $expectedErrorRegex
     * @param bool $serviceErrors
     */
    public function testInvalidServices(
        array $definitions,
        int $expectedErrorCount,
        string $expectedErrorRegex = "",
        bool $serviceErrors = false
    ) {
        if ($expectedErrorCount == 0) {
            $this->compilationHelper->shouldNotReceive("referenceError");
        } else {
            $this->compilationHelper->shouldReceive("referenceError")
                ->with($expectedErrorRegex? \Mockery::pattern($expectedErrorRegex): \Mockery::any())
                ->times($expectedErrorCount)
                ->andReturn($this->error);
        }

        $this->error->shouldReceive("addContext")->times($serviceErrors? $expectedErrorCount: 0);

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $errors = $validator->validate($definitions);

        $this->assertCount($expectedErrorCount, $errors);
    }

    /**
     * @dataProvider serviceDefinitionProvider
     *
     * @param array $services
     * @param array $expectedErrors
     */
    public function testServiceValidation(array $services, array $expectedErrors)
    {
        $expectedErrorCount = count($expectedErrors);

        $this->compilationHelper->shouldReceive("referenceError")->andReturnUsing(
            function ($message) use (&$expectedErrors) {
                $found = false;
                foreach ($expectedErrors as $i => $messageRegex) {
                    if (preg_match($messageRegex, $message)) {
                        $found = true;
                        unset($expectedErrors[$i]);
                    }
                }
                if (!$found) {
                    $this->fail("The validation error '$message' was not expected");
                }
                return $this->error;
            }
        );
        $this->compilationHelper->shouldReceive("getServiceKey")->andReturnArg(0);

        $this->referenceHelper->shouldReceive("checkArrayForReferences")->andReturn([[], []]);
        $this->referenceHelper->shouldReceive("checkServiceReference")->andReturn("foo");
        $this->referenceHelper->shouldReceive("addReference")->andReturn([]);

        $this->error->shouldReceive("addContext")->with("service", \Mockery::any())->times($expectedErrorCount);

        $definitions = [
            "services" => $services
        ];

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $errors = $validator->validate($definitions);
        $this->assertCount($expectedErrorCount, $errors);

        $this->assertEmpty($expectedErrors, "Some expected errors did not occur");
    }

    /**
     * @dataProvider serviceReferenceProvider
     *
     * @param array $services
     * @param int $totalExpectedErrors
     * @param array $expectedErrors
     * @param array $serviceChecks
     */
    public function testServiceDefinitionReferences(
        array $services,
        int $totalExpectedErrors,
        array $expectedErrors = [],
        array $serviceChecks = []
    ) {
        $this->referenceHelper->shouldReceive("checkArrayForReferences")->andReturnUsing(
            function ($array, $options = []) {
                if (!empty($array["options"])) {
                    foreach ($array["options"] as $key => $value) {
                        if (!isset($options[$key]) || $options[$key] !== $value) {
                            $this->fail(
                                "A call to checkArrayForReferences was required to include options that were not set"
                            );
                        }
                    }
                }
                return [
                    array_fill(0, $array["errorCount"], $this->error),
                    []
                ];
            }
        );

        $this->referenceHelper->shouldReceive("checkServiceReference")->andReturnUsing(
            function ($value) use ($serviceChecks) {
                return $serviceChecks[$value] ?? "foo";
            }
        );

        $this->referenceHelper->shouldReceive("addReference")->andReturn([]);

        $this->compilationHelper->shouldReceive("getServiceKey")->andReturnArg(0);

        $this->compilationHelper->shouldReceive("referenceError")->andReturnUsing(
            function ($message) use (&$expectedErrors) {
                $found = false;
                foreach ($expectedErrors as $i => $messageRegex) {
                    if (preg_match($messageRegex, $message)) {
                        $found = true;
                        unset($expectedErrors[$i]);
                    }
                }
                if (!$found) {
                    $this->fail("The validation error '$message' was not expected");
                }
                return $this->error;
            }
        );
        $this->error->shouldReceive("addContext")->with("service", \Mockery::any())->times($totalExpectedErrors);

        $definitions = [
            "services" => $services
        ];

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $errors = $validator->validate($definitions);
        $this->assertCount($totalExpectedErrors, $errors);

        $this->assertEmpty($expectedErrors, "Some expected errors did not occur");

    }

    public function testServiceCircularReferences()
    {
        $definitions = [
            "services" => [
                "one" => [
                    "arguments" => ["id" => "one"]
                ],
                "two" => [
                    "factoryService" => "b",
                    "factoryMethod" => "format"
                ],
                "three" => [
                    "aliasOf" => "c"
                ],
                "four" => [
                    "class" => \DateTime::class,
                    "calls" => [
                        [
                            "method" => "format",
                            "arguments" => ["id" => "four"]
                        ]
                    ]
                ],
                "five" => [
                    "tags" => [
                        [
                            "id" => "tag",
                            "tag" => "tag"
                        ]
                    ]
                ],
                "b" => [
                    "class" => \DateTime::class
                ],
                "c" => [
                    "class" => \DateTime::class
                ]
            ]
        ];

        $referenceMap = [
            "one" => ["a"],
            "two" => "b",
            "three" => "c",
            "four" => ["d"],
            "tag" => "five"
        ];

        $serviceReferences = [
            "one" => ["fine"],
            "two" => ["error"],
            "three" => ["fine"],
            "four" => ["error"],
            "five" => ["error"]
        ];
        $tagReferences = [
            "tag" => ["blah"]
        ];

        foreach ($referenceMap as $key => $references) {
            $returnValue = $key == "tag"? $tagReferences: $serviceReferences;
            $this->referenceHelper
                ->shouldReceive("addReference")
                ->with(\Mockery::any(), $key, $references)
                ->once()
                ->andReturn($returnValue);
        }

        $this->referenceHelper->shouldReceive("checkServiceReference")->andReturnTrue();

        $this->referenceHelper->shouldReceive("checkArrayForReferences")->andReturnUsing(
            function ($array) use ($referenceMap) {
                $id = $array["id"];
                $references = $referenceMap[$id];
                return [
                    [],
                    is_array($references)? $references: []
                ];
            }
        );

        $expectedErrorCount = 0;
        foreach ($serviceReferences as $result) {
            if ($result[0] == "error") {
                ++$expectedErrorCount;
            }
        }

        $this->compilationHelper->shouldReceive("referenceError")->times($expectedErrorCount)->andReturn($this->error);
        $this->compilationHelper->shouldReceive("getServiceKey")->andReturnArg(0);

        $this->referenceHelper->shouldReceive("findCircularReferences")
            ->with(\Mockery::any(), $serviceReferences, $tagReferences)
            ->andReturnUsing(function ($service) use ($serviceReferences) {
                $this->assertArrayHasKey($service, $serviceReferences);
                return $serviceReferences[$service][0] == "error";
            });

        $this->error->shouldReceive("addContext")->with("service", \Mockery::any())->times($expectedErrorCount);

        $validator = new ReferenceValidator($this->referenceHelper, $this->compilationHelper);
        $errors = $validator->validate($definitions);
        $this->assertCount($expectedErrorCount, $errors);
    }

    /**
     * @return array[]
     */
    public function invalidServicesProvider(): array
    {
        return [
            "no services" => [
                ["no" => "services"],
                0
            ],
            "not an array" => [
                ["services" => "foo"],
                1,
                "/not an array/"
            ],
            "definitions not an array" => [
                ["services" => ["foo", "bar"]],
                2,
                "/not an array/",
                true
            ]
        ];
    }

    /**
     * @return array[]
     */
    public function serviceDefinitionProvider(): array
    {
        $class = \DateTime::class;
        $factoryClass = \DatePeriod::class;

        return [
            "invalid class" => [
                [
                    "test" => [
                        "class" => "BadClass"
                    ]
                ],
                [
                    "/.*BadClass.*does not exist.*/"
                ]
            ],
            "invalid factory class" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryClass" => "BadFactoryClass"
                    ],
                ],
                [
                    "/.*BadFactoryClass.*does not exist.*/"
                ]
            ],
            "use of factory service and factory class together" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryService" => "foo",
                        "factoryClass" => $factoryClass
                    ],
                    "foo" => [
                        "class" => $factoryClass
                    ]
                ],
                [
                    "/.*cannot use.*factoryService.*factoryClass.*/i"
                ]
            ],
            "factory method not on factory class" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryClass" => $factoryClass,
                        "factoryMethod" => "foo"
                    ]
                ],
                [
                    "/.*'foo'.*does not exist on.*'$factoryClass'/"
                ]
            ],
            "factory class method is not static" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryClass" => $factoryClass,
                        "factoryMethod" => "getStartDate"
                    ]
                ],
                [
                    "/.*$factoryClass::getStartDate'.*is not.*static.*/"
                ]
            ],
            "factory method not on factory service" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryService" => "foo",
                        "factoryMethod" => "bar"
                    ],
                    "foo" => [
                        "class" => $factoryClass
                    ]
                ],
                [
                    "/.*'bar'.*does not exist on.*'$factoryClass'/"
                ]
            ],
            "valid factory service" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryService" => "foo",
                        "factoryMethod" => "getStartDate"
                    ],
                    "foo" => [
                        "class" => $factoryClass
                    ]
                ],
                []
            ],
            "valid factory class" => [
                [
                    "test" => [
                        "class" => $class,
                        "factoryClass" => \DateInterval::class,
                        "factoryMethod" => "createFromDateString"
                    ]
                ],
                []
            ],
            "call methods not on service class" => [
                [
                    "test" => [
                        "class" => $class,
                        "calls" => [
                            [
                                "method" => "foo"
                            ],
                            [
                                "method" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "/.*call method.*'foo'.* does not exist on.*'$class'/",
                    "/.*call method.*'bar'.* does not exist on.*'$class'/"
                ]
            ],
            "valid calls" => [
                [
                    "test" => [
                        "class" => $class,
                        "calls" => [
                            [
                                "method" => "add"
                            ],
                            [
                                "method" => "sub"
                            ]
                        ]
                    ]
                ],
                []
            ]
        ];
    }

    /**
     * @return array[]
     */
    public function serviceReferenceProvider(): array
    {
        return [
            "check argument references" => [
                [
                    "test" => [
                        "arguments" => [
                            "errorCount" => 3
                        ]
                    ]
                ],
                3
            ],
            "check valid argument references" => [
                [
                    "test" => [
                        "arguments" => [
                            "errorCount" => 0
                        ]
                    ]
                ],
                0
            ],
            "check call argument references" => [
                [
                    "test" => [
                        "class" => \DateTime::class,
                        "calls" => [
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 2
                                ]
                            ],
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 3
                                ]
                            ]
                        ]
                    ]
                ],
                5
            ],
            "check valid call argument references" => [
                [
                    "test" => [
                        "class" => \DateTime::class,
                        "calls" => [
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 0
                                ]
                            ],
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 0
                                ]
                            ]
                        ]
                    ]
                ],
                0
            ],
            "check tag references" => [
                [
                    "test" => [
                        "tags" => [
                            [
                                "errorCount" => 6,
                                "options" => [
                                    "skipServices" => true
                                ],
                                "tag" => "foo"
                            ]
                        ]
                    ]
                ],
                6
            ],
            "check valid tag references" => [
                [
                    "test" => [
                        "tags" => [
                            [
                                "errorCount" => 0,
                                "options" => [
                                    "skipServices" => true
                                ],
                                "tag" => "foo"
                            ]
                        ]
                    ]
                ],
                0
            ],
            "check reference error combinations" => [
                [
                    "test" => [
                        "class" => \DateTime::class,
                        "arguments" => [
                            "errorCount" => 4
                        ],
                        "calls" => [
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 2
                                ]
                            ],
                            [
                                "method" => "format",
                                "arguments" => [
                                    "errorCount" => 3
                                ]
                            ]
                        ],
                        "tags" => [
                            [
                                "errorCount" => 2,
                                "tag" => "foo"
                            ]
                        ]
                    ]
                ],
                11
            ],
            "check factoryService reference errors are applied" => [
                [
                    "test" => [
                        "factoryService" => "foo",
                        "factoryMethod" => "bar"
                    ]
                ],
                1,
                [
                    "/.*'foo'.*does not exist.*/"
                ],
                [
                    "foo" => false
                ]
            ],
            "check valid factoryService reference" => [
                [
                    "test" => [
                        "factoryService" => "foo",
                        "factoryMethod" => "format"
                    ],
                    "foo" => [
                        "class" => \DateTime::class
                    ]
                ],
                0,
                [],
                [
                    "foo" => "foo"
                ]
            ],
            "check aliasOf reference errors are applied" => [
                [
                    "test" => [
                        "aliasOf" => "foo"
                    ]
                ],
                1,
                [
                    "/.*'foo'.*does not exist.*/"
                ],
                [
                    "foo" => false
                ]
            ],
            "check valid aliasOf reference" => [
                [
                    "test" => [
                        "aliasOf" => "foo"
                    ]
                ],
                0,
                [],
                [
                    "foo" => "foo"
                ]
            ]

        ];
    }

}
