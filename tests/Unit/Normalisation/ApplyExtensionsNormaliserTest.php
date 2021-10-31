<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\Normalisation\ApplyExtensionsNormaliser;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ApplyExtensionsNormaliserTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use NormalisationErrorTestTrait;
    use ExpectedDefinitionsTestTrait;

    /**
     * @var CompilationHelper|MockInterface
     */
    protected $helper;

    /**
     * @var ValidationError|MockInterface
     */
    protected $error;

    public function setUp(): void
    {
        $this->setupErrorMocks();
    }

    /**
     * @dataProvider normalisationProvider
     *
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array $expectedErrors
     */
    public function testNormalisation(array $definitions, array $expectedDefinitions, array $expectedErrors = [])
    {
        $expectedErrorCount = count($expectedErrors);
        $this->configureErrorTests($expectedErrors);

        $normaliser = new ApplyExtensionsNormaliser($this->helper);

        [$normalisedDefinitions, $errors] = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, $expectedDefinitions);

        $this->assertCount($expectedErrorCount, $errors);
        $this->assertEmpty($expectedErrors);
    }

    /**
     * @return array[]
     */
    public function normalisationProvider(): array
    {

        $existingCall = ["foo" => "bar"];
        $existingTag = ["baz" => "fiz"];

        $standardDefinitions = [
            "services" => [
                "test" => [
                    "calls" => [
                        $existingCall
                    ],
                    "tags" => [
                        $existingTag
                    ]
                ]
            ]
        ];

        return [
            "No extensions" => [
                $standardDefinitions,
                [
                    "services>test>calls" => [
                        $existingCall
                    ],
                    "services>test>tags" => [
                        $existingTag
                    ]
                ]
            ],
            "Extra call" => [
                array_merge($standardDefinitions, [
                    "extensions" => [
                        "test" => [
                            "calls" => [
                                ["added" => "call"]
                            ]
                        ]
                    ]
                ]),
                [
                    "services>test>calls>0" => $existingCall,
                    "services>test>calls>1" => ["added" => "call"],
                    "services>test>tags" => [
                        $existingTag
                    ]
                ]
            ],
            "Extra tag" => [
                array_merge($standardDefinitions, [
                    "extensions" => [
                        "test" => [
                            "tags" => [
                                ["added" => "tag"]
                            ]
                        ]
                    ]
                ]),
                [
                    "services>test>tags>0" => $existingTag,
                    "services>test>tags>1" => ["added" => "tag"],
                    "services>test>calls" => [
                        $existingCall
                    ]
                ]
            ],
            "Multiple calls and tags" => [
                array_merge($standardDefinitions, [
                    "extensions" => [
                        "test" => [
                            "calls" => [
                                ["added" => "one"],
                                ["added" => "two"],
                                ["added" => "three"]
                            ],
                            "tags" => [
                                ["added" => "four"],
                                ["added" => "five"]
                            ]
                        ]
                    ]
                ]),
                [
                    "services>test>calls>0" => $existingCall,
                    "services>test>calls>1" => ["added" => "one"],
                    "services>test>calls>2" => ["added" => "two"],
                    "services>test>calls>3" => ["added" => "three"],
                    "services>test>tags>0" => $existingTag,
                    "services>test>tags>1" => ["added" => "four"],
                    "services>test>tags>2" => ["added" => "five"],
                ]
            ],
            "Adding calls and tags where the service has neither" => [
                [
                    "services" => [
                        "test" => [
                            "class" => "blah"
                        ]
                    ],
                    "extensions" => [
                        "test" => [
                            "calls" => [
                                ["added" => "one"]
                            ],
                            "tags" => [
                                ["added" => "two"]
                            ]
                        ]
                    ]
                ],
                [
                    "services>test>calls>0" => ["added" => "one"],
                    "services>test>tags>0" => ["added" => "two"],
                ]
            ],
            "Multiple extensions" => [
                [
                    "services" => [
                        "one" => [
                            "calls" => [
                                ["existing" => "one"],
                                ["existing" => "two"]
                            ]
                        ],
                        "two" => [
                            "calls" => [
                                ["existing" => "three"]
                            ],
                            "tags" => [
                                ["existing" => "four"]
                            ]
                        ]
                    ],
                    "extensions" => [
                        "one" => [
                            "calls" => [
                                ["added" => "one"]
                            ],
                            "tags" => [
                                ["added" => "two"]
                            ]
                        ],
                        "two" => [
                            "tags" => [
                                ["added" => "three"]
                            ]
                        ]
                    ]
                ],
                [
                    "services>one>calls>1" => ["existing" => "two"],
                    "services>one>calls>2" => ["added" => "one"],
                    "services>one>tags>0" => ["added" => "two"],
                    "services>two>calls>0" => ["existing" => "three"],
                    "services>two>tags>0" => ["existing" => "four"],
                    "services>two>tags>1" => ["added" => "three"],
                ]
            ],
            "Extension for missing service" => [
                array_merge($standardDefinitions, [
                    "extensions" => [
                        "missingService" => [
                            "calls" => [
                                ["foo" => "bar"]
                            ]
                        ]
                    ]
                ]),
                [],
                [
                    "/.*missingService.*does not exist.*/"
                ]
            ],
            "Errors don't prevent other extensions being applied" => [
                array_merge($standardDefinitions, [
                    "extensions" => [
                        "test" => [
                            "calls" => [
                                ["added" => "one"]
                            ]
                        ],
                        "missingService" => [
                            "calls" => [
                                ["foo" => "bar"]
                            ]
                        ]
                    ]
                ]),
                [
                    "services>test>calls>0" => $existingCall,
                    "services>test>calls>1" => ["added" => "one"],
                ],
                [
                    "/.*missingService.*does not exist.*/"
                ]
            ]
        ];
    }

}
