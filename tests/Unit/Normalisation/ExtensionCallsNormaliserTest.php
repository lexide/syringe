<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Normalisation\ExtensionCallsNormaliser;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ExtensionCallsNormaliserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use ExpectedDefinitionsTestTrait;

    /**
     * @dataProvider extensionsProvider
     *
     * @param $definitions
     * @param $expectedDefinitions
     */
    public function testNormalisation($definitions, $expectedDefinitions)
    {
        $normaliser = new ExtensionCallsNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
    }

    /**
     * @return array[]
     */
    public function extensionsProvider(): array
    {
        return [
            "No extensions" => [
                [
                    "testNS" => [
                        "nothing" => "here"
                    ]
                ],
                [
                    "testNS>nothing" => "here"
                ]
            ],
            "Extensions normalised" => [
                [
                    "testNS" => [
                        "extensions" => [
                            "service" => [
                                "this is a call",
                                "to all my",
                                "past resignations"
                            ]
                        ]
                    ]
                ],
                [
                    "testNS>extensions>service>calls>1" => "to all my"
                ]
            ],
            "Extensions already correct" => [
                [
                    "testNS" => [
                        "extensions" => [
                            "service" => [
                                "calls" => [
                                    "this is a call",
                                    "to all my",
                                    "past resignations"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "testNS>extensions>service>calls>2" => "past resignations"
                ]
            ],
            "Extensions normalised in multiple namespaces" => [
                [
                    "NS-one" => [
                        "extensions" => [
                            "service-one" => [
                                "one",
                                "two"
                            ],
                            "service-two" => [
                                "three"
                            ]
                        ]
                    ],
                    "NS-two" => [
                        "extensions" => [
                            "service-three" => [
                                "four",
                                "five"
                            ]
                        ]
                    ]
                ],
                [
                    "NS-one>extensions>service-one>calls>0" => "one",
                    "NS-one>extensions>service-one>calls>1" => "two",
                    "NS-one>extensions>service-two>calls>0" => "three",
                    "NS-two>extensions>service-three>calls>0" => "four",
                    "NS-two>extensions>service-three>calls>1" => "five",
                ]
            ]

        ];
    }

}
