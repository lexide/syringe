<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\Normalisation\NamespaceNormaliser;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class NamespaceNormaliserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use ExpectedDefinitionsTestTrait;
    use NormalisationErrorTestTrait;

    public function setUp(): void
    {
        $this->setupErrorMocks();
        $this->helper->shouldReceive("isServiceReference")->passthru();
        $this->helper->shouldReceive("getServiceKey")->passthru();
        $this->helper->shouldReceive("getServiceReference")->passthru();
    }

    /**
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $missingDefinitions
     */
    protected function standardTest(
        array $definitions,
        array $expectedDefinitions,
        array $missingDefinitions = []
    ) {
        $expectedErrors = [];
        $this->configureErrorTests($expectedErrors);

        $normaliser = new NamespaceNormaliser($this->helper);

        [$normalisedDefinitions] = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
        $this->testMissingDefinitions($normalisedDefinitions, $missingDefinitions);
    }

    /**
     * @dataProvider aliasDefinitionsProvider
     *
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $missingDefinitions
     */
    public function testNamespacingAliases(
        array $definitions,
        array $expectedDefinitions,
        array $missingDefinitions = []
    ) {
        $this->helper->shouldReceive("findNextParameter")->andReturnNull();

        $this->standardTest($definitions, $expectedDefinitions, $missingDefinitions);
    }

    public function aliasDefinitionsProvider(): array
    {
        return [
            "service aliasing (same namespace)" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ],
                            "two" => [
                                "aliasOf" => "@one"
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>foo" => "bar",
                    "services>namespace.two>aliasOf" => "@namespace.one",
                ]
            ],
            "service aliasing (alias second)" => [
                [
                    "ns1" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "ns1.one" => [
                                "aliasOf" => "@two"
                            ],
                            "two" => [
                                "foo" => "baz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>aliasOf" => "@ns2.two",
                    "services>ns2.two>foo" => "baz",
                ],
                [
                    "services>ns1.one>foo"
                ]
            ],
            "service aliasing (alias first)" => [
                [
                    "ns1" => [
                        "services" => [
                            "ns2.two" => [
                                "aliasOf" => "@one"
                            ],
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "two" => [
                                "foo" => "baz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>foo" => "bar",
                    "services>ns2.two>aliasOf" => "@ns1.one",
                ],
                [
                    "services>ns2.two>foo"
                ]
            ],
            "last alias wins" => [
                [
                    "ns1" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "ns1.one" => [
                                "aliasOf" => "@two"
                            ],
                            "two" => [
                                "foo" => "baz"
                            ]
                        ]
                    ],
                    "ns3" => [
                        "services" => [
                            "ns1.one" => [
                                "aliasOf" => "@three"
                            ],
                            "three" => [
                                "foo" => "fuz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>aliasOf" => "@ns3.three",
                    "services>ns2.two>foo" => "baz",
                    "services>ns3.three>foo" => "fuz",
                ]
            ],
            "external alias wins" => [
                [
                    "ns1" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ],
                            "ns2.two" => [
                                "aliasOf" => "@one"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "two" => [
                                "aliasOf" => "@three"
                            ],
                            "three" => [
                                "foo" => "baz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>foo" => "bar",
                    "services>ns2.two>aliasOf" => "@ns1.one",
                    "services>ns2.three>foo" => "baz",
                ]
            ],
            "alias chains" => [
                [
                    "ns1" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "ns1.one" => [
                                "aliasOf" => "@two"
                            ],
                            "two" => [
                                "foo" => "baz"
                            ]
                        ]
                    ],
                    "ns3" => [
                        "services" => [
                            "ns2.two" => [
                                "aliasOf" => "@three"
                            ],
                            "three" => [
                                "foo" => "fuz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>aliasOf" => "@ns2.two",
                    "services>ns2.two>aliasOf" => "@ns3.three",
                    "services>ns3.three>foo" => "fuz",
                ],
                [
                    "services>ns1.one>foo",
                    "services>ns2.two>foo"
                ]
            ]
        ];
    }

    public function testKeyCollisions()
    {
        $definitions = [
            "ns1" => [
                "services" => [
                    "one" => [
                        "foo" => "bar"
                    ]
                ]
            ],
            "ns2" => [
                "services" => [
                    "ns1.one" => [
                        "baz" => "fuz"
                    ]
                ]
            ]
        ];

        $expectedErrors = [
            "/.*'ns1\\.one'.*'ns2' namespace.*already been defined.*/"
        ];
        $this->configureErrorTests($expectedErrors);

        $this->helper->shouldReceive("findNextParameter")->andReturnNull();

        $normaliser = new NamespaceNormaliser($this->helper);

        [$normalisedDefinitions, $errors] = $normaliser->normalise($definitions);

        $this->assertNotEmpty($errors);
        $this->assertEmpty($expectedErrors);
    }

    /**
     * @dataProvider extensionsDefinitionsProvider
     *
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $missingDefinitions
     */
    public function testNamespacingExtensions(
        array $definitions,
        array $expectedDefinitions,
        array $missingDefinitions = []
    ) {
        $this->helper->shouldReceive("findNextParameter")->andReturnNull();

        $this->standardTest($definitions, $expectedDefinitions, $missingDefinitions);
    }

    /**
     * @return array[]
     */
    public function extensionsDefinitionsProvider(): array
    {
        return [
            "namespace extensions" => [
                [
                    "namespace" => [
                        "extensions" => [
                            "one" => [
                                "calls" => [[
                                    "foo" => "bar"
                                ]]
                            ]
                        ]
                    ]
                ],
                [
                    "extensions>namespace.one>calls>0>foo" => "bar"
                ]
            ],
            "don't namespace if already namespaced" => [
                [
                    "ns1" => [
                        "extensions" => [
                            "ns2.one" => [
                                "calls" => [[
                                    "foo" => "bar"
                                ]]
                            ]
                        ]
                    ],
                    "ns2" => []
                ],
                [
                    "extensions>ns2.one>calls>0>foo" => "bar"
                ],
                [
                    "extensions>ns1.ns2.one"
                ]
            ],
            "namespace extension references" => [
                [
                    "namespace" => [
                        "extensions" => [
                            "one" => [
                                "calls" => [
                                    [
                                        "arguments" => [
                                            "@two",
                                            "@three"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "extensions>namespace.one>calls>0>arguments>0" => "@namespace.two",
                    "extensions>namespace.one>calls>0>arguments>1" => "@namespace.three"
                ]
            ],
            "merge extensions - calls" => [
                [
                    "ns1" => [
                        "extensions" => [
                            "ns2.one" => [
                                "calls" => [
                                    [
                                        "one" => 1
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "ns2" => [
                        "extensions" => [
                            "ns2.one" => [
                                "calls" => [
                                    [
                                        "two" => 2
                                    ],
                                    [
                                        "three" => 3
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "extensions>ns2.one>calls>0>one" => 1,
                    "extensions>ns2.one>calls>1>two" => 2,
                    "extensions>ns2.one>calls>2>three" => 3,
                ]
            ],
            "merge extensions - tags" => [
                [
                    "ns1" => [
                        "extensions" => [
                            "ns2.one" => [
                                "tags" => [
                                    [
                                        "one" => 1
                                    ],
                                    [
                                        "two" => 2
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "ns2" => [
                        "extensions" => [
                            "ns2.one" => [
                                "tags" => [
                                    [
                                        "three" => 3
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "extensions>ns2.one>tags>0>one" => 1,
                    "extensions>ns2.one>tags>1>two" => 2,
                    "extensions>ns2.one>tags>2>three" => 3,
                ]
            ],
            "merge extensions - calls and tags" => [
                [
                    "ns1" => [
                        "extensions" => [
                            "ns2.one" => [
                                "tags" => [
                                    [
                                        "one" => 1
                                    ],
                                    [
                                        "two" => 2
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "ns2" => [
                        "extensions" => [
                            "ns2.one" => [
                                "calls" => [
                                    [
                                        "three" => 3,
                                    ],
                                    [
                                        "four" => 4
                                    ]
                                ],
                                "tags" => [
                                    [
                                        "five" => 5
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "extensions>ns2.one>tags>0>one" => 1,
                    "extensions>ns2.one>tags>1>two" => 2,
                    "extensions>ns2.one>tags>2>five" => 5,
                    "extensions>ns2.one>calls>0>three" => 3,
                    "extensions>ns2.one>calls>1>four" => 4,
                ]
            ]
        ];
    }

    /**
     * @dataProvider parameterDefinitionsProvider
     *
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $missingDefinitions
     */
    public function testNamespacingParameters(
        array $definitions,
        array $expectedDefinitions,
        array $missingDefinitions = []
    ) {
        $this->helper->shouldReceive("findNextParameter")->passthru();
        $this->helper->shouldReceive("replaceParameterReference")->passthru();

        $this->standardTest($definitions, $expectedDefinitions, $missingDefinitions);
    }

    public function parameterDefinitionsProvider(): array
    {
        return [
            //*
            "parameters as calls keys and subkeys are NOT namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "calls" => [
                                    "%two%" => [
                                        "method" => "foo",
                                        "%three%" => "bar"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>calls>%two%>method" => "foo",
                    "services>namespace.one>calls>%two%>%three%" => "bar"
                ]
            ],
            "parameters as arguments keys are NOT namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "arguments" => [
                                    "%two%" => "foo"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>arguments>%two%" => "foo"
                ]
            ],
            "parameters are namespaced" => [
                [
                    "namespace" => [
                        "parameters" => [
                            "one" => "%two%"
                        ]
                    ]
                ],
                [
                    "parameters>namespace.one" => "%namespace.two%"
                ]
            ],
            "already namespaced parameters are ignored" => [
                [
                    "namespace" => [
                        "parameters" => [
                            "one" => "%namespace.two%"
                        ]
                    ]
                ],
                [
                    "parameters>namespace.one" => "%namespace.two%"
                ]
            ],
            "nested parameters are namespaced" => [
                [
                    "namespace" => [
                        "parameters" => [
                            "one" => "%two% and %three%"
                        ]
                    ]
                ],
                [
                    "parameters>namespace.one" => "%namespace.two% and %namespace.three%"
                ]
            ],
            "parameters in deep arrays are namespaced" => [
                [
                    "namespace" => [
                        "parameters" => [
                            "one" => [
                                "%two%" => [
                                    "foo" => [
                                        "bar" => "%three%"
                                    ],
                                    "baz" => "%four%"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "parameters>namespace.one>%namespace.two%>foo>bar" => "%namespace.three%",
                    "parameters>namespace.one>%namespace.two%>baz" => "%namespace.four%"
                ]
            ],
            "namespaced parameters overwrite (external wins)" => [
                [
                    "ns1" => [
                        "parameters" => [
                            "ns2.two" => "foo"
                        ]
                    ],
                    "ns2" => [
                        "parameters" => [
                            "two" => "bar"
                        ]
                    ]
                ],
                [
                    "parameters>ns2.two" => "foo"
                ]
            ],
            "namespaced parameters overwrite (last wins)" => [
                [
                    "ns1" => [
                        "parameters" => [
                            "one" => "foo"
                        ]
                    ],
                    "ns2" => [
                        "parameters" => [
                            "ns1.one" => "bar"
                        ]
                    ],
                    "ns3" => [
                        "parameters" => [
                            "ns1.one" => "baz"
                        ]
                    ]
                ],
                [
                    "parameters>ns1.one" => "baz"
                ]
            ],//*/
        ];
    }

    /**
     * @dataProvider serviceDefinitionsProvider
     *
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $missingDefinitions
     */
    public function testNamespacingServices(
        array $definitions,
        array $expectedDefinitions,
        array $missingDefinitions = []
    ) {
        $this->helper->shouldReceive("findNextParameter")->andReturnNull();

        $this->standardTest($definitions, $expectedDefinitions, $missingDefinitions);
    }

    public function serviceDefinitionsProvider(): array
    {
        return [
            "keys are namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>foo" => "bar"
                ]
            ],
            "references are namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "factoryService" => "@two"
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>factoryService" => "@namespace.two"
                ]
            ],
            "already namespaced references are ignored" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "factoryService" => "@namespace.two"
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>factoryService" => "@namespace.two"
                ]
            ],
            "references in deep arrays are namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "foo" => [
                                    "bar" => [
                                        "baz" => "@two"
                                    ],
                                    "fuz" => "@three"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>foo>bar>baz" => "@namespace.two",
                    "services>namespace.one>foo>fuz" => "@namespace.three"
                ]
            ],
            "service references in call argument lists are namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "calls" => [
                                    [
                                        "method" => "foo",
                                        "arguments" => [
                                            "@two",
                                            "bar",
                                            [
                                                "id" => "@three"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>calls>0>arguments>0" => "@namespace.two",
                    "services>namespace.one>calls>0>arguments>2>id" => "@namespace.three"
                ]
            ],
            "references in arguments lists are namespaced" => [
                [
                    "namespace" => [
                        "services" => [
                            "one" => [
                                "arguments" => [
                                    "@two",
                                    [
                                        "foo" => "@three"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "services>namespace.one>arguments>0" => "@namespace.two",
                    "services>namespace.one>arguments>1>foo" => "@namespace.three"
                ]
            ],
            "multiple namespaces" => [
                [
                    "ns1" => [
                        "services" => [
                            "one" => [
                                "foo" => "bar"
                            ]
                        ]
                    ],
                    "ns2" => [
                        "services" => [
                            "one" => [
                                "foo" => "baz"
                            ]
                        ]
                    ]
                ],
                [
                    "services>ns1.one>foo" => "bar",
                    "services>ns2.one>foo" => "baz",
                ]
            ]
        ];
    }
}
