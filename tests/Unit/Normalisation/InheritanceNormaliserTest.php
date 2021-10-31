<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Normalisation\InheritanceNormaliser;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class InheritanceNormaliserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use NormalisationErrorTestTrait;
    use ExpectedDefinitionsTestTrait;

    public function setUp(): void
    {
        $this->setupErrorMocks();
    }

    /**
     * @dataProvider inheritanceProvider
     *
     * @param $services
     * @param $expectedDefinitions
     * @param $missingDefinitions
     */
    public function testInheritance($services, $expectedDefinitions, $missingDefinitions)
    {
        $normaliser = new InheritanceNormaliser($this->helper);

        $definitions = [
            "services" => $services
        ];

        $expectedErrors = [];
        $this->configureErrorTests($expectedErrors);

        [$normalisedDefinitions] = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
        $this->testMissingDefinitions($normalisedDefinitions, $missingDefinitions);

    }

    public function testInheritedServiceMustBeAbstract()
    {
        $normaliser = new InheritanceNormaliser($this->helper);

        $definitions = [
            "services" => [
                "one" => [
                    "class" => "blah",
                    "foo" => "bar"
                ],
                "two" => [
                    "extends" => "one",
                    "foo" => "baz"
                ]
            ]
        ];

        $expectedDefinitions = [
            "services>two>foo" => "baz"
        ];

        $expectedErrors = [
            "/not an abstract service/"
        ];
        $this->configureErrorTests($expectedErrors);

        [$normalisedDefinitions, $errors] = $normaliser->normalise($definitions);

        $this->assertCount(1, $errors);
        $this->assertEmpty($expectedErrors);
        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
    }

    public function testInheritanceChaining()
    {
        $normaliser = new InheritanceNormaliser($this->helper);

        $definitions = [
            "services" => [
                "one" => [
                    "abstract" => true,
                    "extends" => "two",
                    "foo" => "one",
                    "calls" => [
                        "one"
                    ]
                ],
                "two" => [
                    "abstract" => true,
                    "extends" => "three",
                    "foo" => "two",
                    "tags" => [
                        "two"
                    ]
                ],
                "three" => [
                    "abstract" => true,
                    "foo" => "three",
                    "three" => "three",
                    "calls" => [
                        "three"
                    ],
                    "tags" => [
                        "three"
                    ]
                ],
                "four" => [
                    "extends" => "one",
                    "foo" => "four",
                    "calls" => [
                        "four"
                    ],
                    "tags" => [
                        "four"
                    ]
                ]
            ]
        ];

        $expectedDefinitions = [
            "services>four>foo" => "four",
            "services>four>calls>0" => "four",
            "services>four>calls>1" => "one",
            "services>four>calls>2" => "three",
            "services>four>tags>0" => "four",
            "services>four>tags>1" => "two",
            "services>four>tags>2" => "three",
        ];

        $expectedErrors = [];
        $this->configureErrorTests($expectedErrors);

        [$normalisedDefinitions] = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
    }

    public function testCircularInheritanceChainDetection()
    {
        $normaliser = new InheritanceNormaliser($this->helper);

        $definitions = [
            "services" => [
                "one" => [
                    "abstract" => true,
                    "extends" => "two",
                    "one" => "one"
                ],
                "two" => [
                    "abstract" => true,
                    "extends" => "three",
                    "foo" => "two"
                ],
                "three" => [
                    "abstract" => true,
                    "extends" => "one",
                    "foo" => "three"
                ],
                "four" => [
                    "extends" => "one",
                    "foo" => "four"
                ]
            ]
        ];

        $expectedErrors = [
            "/has circular inheritance/"
        ];
        $this->configureErrorTests($expectedErrors);

        [$normalisedDefinitions, $errors] = $normaliser->normalise($definitions);

        $this->assertCount(1, $errors);
        $this->assertEmpty($expectedErrors);
        $this->testExpectedDefinitions($normalisedDefinitions, ["services>four>foo" => "four"]);
        $this->testMissingDefinitions($normalisedDefinitions, ["services>four>one"]);
    }

    /**
     * @return array[]
     */
    public function inheritanceProvider(): array
    {
        return [
            "no inheritance" => [
                [
                    "one" => [
                        "foo" => "bar"
                    ],
                    "two" => [
                        "baz" => "fuz"
                    ]
                ],
                [
                    "services>one>foo" => "bar",
                    "services>two>baz" => "fuz"
                ],
                []
            ],
            "simple inheritance" => [
                [
                    "one" => [
                        "abstract" => true,
                        "foo" => "one"
                    ],
                    "two" => [
                        "extends" => "one",
                        "bar" => "two"
                    ]
                ],
                [
                    "services>two>foo" => "one",
                    "services>two>bar" => "two",
                ],
                [
                    "services>one"
                ]
            ],
            "child overwrites parent" => [
                [
                    "one" => [
                        "abstract" => true,
                        "foo" => "one"
                    ],
                    "two" => [
                        "extends" => "one",
                        "foo" => "two"
                    ]
                ],
                [
                    "services>two>foo" => "two"
                ],
                [
                    "services>one"
                ]
            ],
            "calls are merged" => [
                [
                    "one" => [
                        "abstract" => true,
                        "calls" => [
                            ["one" => "one"],
                            ["two" => "one"]
                        ]
                    ],
                    "two" => [
                        "extends" => "one",
                        "calls" => [
                            ["three" => "two"],
                            ["four" => "two"]
                        ]
                    ]
                ],
                [
                    "services>two>calls>0>three" => "two",
                    "services>two>calls>3>two" => "one",
                ],
                [
                    "services>one"
                ]
            ],
            "tags are merged" => [
                [
                    "one" => [
                        "abstract" => true,
                        "tags" => [
                            ["one" => "one"]
                        ]
                    ],
                    "two" => [
                        "extends" => "one",
                        "tags" => [
                            ["two" => "two"]
                        ]
                    ]
                ],
                [
                    "services>two>tags>0>two" => "two",
                    "services>two>tags>1>one" => "one",
                ],
                [
                    "services>one"
                ]
            ],
            "arrays are replaced recursively" => [
                [
                    "one" => [
                        "abstract" => true,
                        "foo" => [
                            "bar" => "one",
                            "baz" => "one"
                        ]
                    ],
                    "two" => [
                        "extends" => "one",
                        "foo" => [
                            "bar" => "two",
                            "fuz" => "two"
                        ]
                    ]
                ],
                [
                    "services>two>foo>bar" => "two",
                    "services>two>foo>baz" => "one",
                    "services>two>foo>fuz" => "two"
                ],
                [
                    "services>one"
                ]
            ],
            "many service inherit from one" => [
                [
                    "one" => [
                        "abstract" => true,
                        "foo" => "one"
                    ],
                    "two" => [
                        "extends" => "one",
                        "bar" => "two"
                    ],
                    "three" => [
                        "extends" => "one",
                        "baz" => "three"
                    ]
                ],
                [
                    "services>two>foo" => "one",
                    "services>two>bar" => "two",
                    "services>three>foo" => "one",
                    "services>three>baz" => "three",
                ],
                [
                    "services>one",
                    "services>two>baz",
                    "services>three>bar"
                ]
            ]
        ];
    }

}
