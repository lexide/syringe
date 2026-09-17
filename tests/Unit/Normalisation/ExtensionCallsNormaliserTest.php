<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Normalisation\ExtensionCallsNormaliser;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtensionCallsNormaliserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use ExpectedDefinitionsTestTrait;

    #[DataProvider("extensionsProvider")]
    public function testNormalisation($definitions, $expectedDefinitions)
    {
        $normaliser = new ExtensionCallsNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);
    }

    /**
     * @return array[]
     */
    public static function extensionsProvider(): array
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
            "Keyed call names are removed" => [
                [
                    "testNS" => [
                        "extensions" => [
                            "service" => [
                                "foo" => "bar",
                                "baz" => "fiz"
                            ]
                        ]
                    ]
                ],
                [
                    "testNS>extensions>service>calls>0" => "bar",
                    "testNS>extensions>service>calls>1" => "fiz"
                ]
            ],
            "Extensions already correct" => [
                [
                    "testNS" => [
                        "extensions" => [
                            "serviceOne" => [
                                "calls" => [
                                    "this is a call",
                                    "to all my",
                                    "past resignations"
                                ]
                            ],
                            "serviceTwo" => [
                                "tags" => [
                                    "it's been too long"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "testNS>extensions>serviceOne>calls>2" => "past resignations",
                    "testNS>extensions>serviceTwo>tags>0" => "it's been too long"
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
