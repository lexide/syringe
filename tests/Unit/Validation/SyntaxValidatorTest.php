<?php

namespace Lexide\Syringe\Test\Unit\Validation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Reference\ReferenceHelper;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\TypeValidator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SyntaxValidatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected ReferenceHelper|MockInterface $referenceHelper;
    protected ErrorHelper|MockInterface $errorHelper;
    protected TypeValidator|MockInterface $typeValidator;

    public function setUp(): void
    {
        $this->referenceHelper = \Mockery::mock(ReferenceHelper::class);
        $this->referenceHelper->shouldReceive("isServiceReference")->passthru();

        $this->errorHelper = \Mockery::mock(ErrorHelper::class);
        $this->errorHelper->shouldIgnoreMissing(\Mockery::mock(SyringeError::class));

        $this->typeValidator = \Mockery::mock(TypeValidator::class);
    }

    protected function standardTest(array $schemas, array $definition, int $errorCount = 0): void
    {
        $validator = new SyntaxValidator($this->referenceHelper, $this->errorHelper, $this->typeValidator, $schemas);

        $this->assertCount($errorCount, $validator->validate($definition, "test.yml"));
    }

    #[DataProvider("typeProvider")]
    public function testTypeValidation(array $schemas, array $definition, bool $success, bool $checkTypeResult = false)
    {
        $this->typeValidator->shouldReceive("checkType")->andReturn($checkTypeResult);
        $this->standardTest($schemas, $definition, $success ? 0 : 1);
    }

    public function testChildrenValidationSuccess()
    {
        $schemas = [
            "syringe" => [
                "children" => [
                    "one" => [
                        "type" => "string"
                    ],
                    "two" => [
                        "type" => "number"
                    ],
                    "three" => [
                        "type" => "bool"
                    ]
                ]
            ]
        ];

        $definition = [
            "one" => "foo",
            "two" => 12345,
            "three" => false
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnTrue();

        $this->standardTest($schemas, $definition);
    }

    public function testChildrenValidationFailure()
    {
        $schemas = [
            "syringe" => [
                "children" => [
                    "one" => [
                        "type" => "string"
                    ],
                    "two" => [
                        "type" => "number"
                    ],
                    "three" => [
                        "type" => "bool"
                    ]
                ]
            ]
        ];

        $definition = [
            "one" => 12345,
            "two" => false,
            "three" => "foo"
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnFalse();

        $this->standardTest($schemas, $definition, 3);
    }

    public function testExtraChildrenValidationFailure()
    {
        $schemas = [
            "syringe" => [
                "children" => [
                    "one" => [
                        "type" => "string"
                    ],
                    "two" => [
                        "type" => "number"
                    ],
                    "three" => [
                        "type" => "bool"
                    ]
                ]
            ]
        ];

        $definition = [
            "one" => "foo",
            "two" => 12345,
            "three" => false,
            "four" => "bar"
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnTrue();

        $this->standardTest($schemas, $definition, 1);
    }

    public function testElementValidationSuccess()
    {
        $schemas = [
            "syringe" => [
                "element" => [
                    "type" => "string"
                ]
            ]
        ];

        $definition = [
            "one",
            "two",
            "three"
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnTrue();

        $this->standardTest($schemas, $definition);
    }

    public function testElementValidationFailure()
    {
        $schemas = [
            "syringe" => [
                "element" => [
                    "type" => "bool"
                ]
            ]
        ];

        $definition = [
            "one",
            "two",
            "three",
            "four",
            "five"
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnFalse();

        $this->standardTest($schemas, $definition, 5);
    }

    #[DataProvider("requiredChildrenSuccessProvider")]
    public function testRequiredChildrenValidationSuccess(array $schemas, array $definition)
    {
        $this->standardTest($schemas, $definition);
    }

    #[DataProvider("requiredChildrenFailureProvider")]
    public function testRequiredChildrenValidationFailure(array $schemas, array $definition, int $errorCount = 1)
    {
        $this->standardTest($schemas, $definition, $errorCount);
    }

    #[DataProvider("xorProvider")]
    public function testEmptyValidation(bool $shouldBeEmpty, bool $isEmpty, bool $errorExpected)
    {

        $schemas = [
            "syringe" => [
                "empty" => $shouldBeEmpty
            ]
        ];

        $definition = $isEmpty? []: ["not empty"];

        $validator = new SyntaxValidator($this->referenceHelper, $this->errorHelper, $this->typeValidator, $schemas);

        $errors = $validator->validate($definition, "test.yml");

        $this->assertSame(
            $errorExpected,
            !empty($errors),
            $errorExpected
                ? "An error was expected but did not occur"
                : "An unexpected error occurred"
        );

    }

    public function testValidationWarning()
    {
        $schemas = [
            "syringe" => [
                "warning" => "This is a warning"
            ]
        ];

        $definition = ["a thing"];

        $this->errorHelper->shouldReceive("warning")->once()->andReturn(\Mockery::mock(SyringeError::class));

        $validator = new SyntaxValidator($this->referenceHelper, $this->errorHelper, $this->typeValidator, $schemas);
        $errors = $validator->validate($definition, "test.yml");

        $this->assertCount(1, $errors);
    }

    public function testOneOfValidationSuccess()
    {
        $schemas = [
            "syringe" => [
                "element" => [
                    "oneOf" => [
                        [
                            "type" => "string"
                        ],
                        [
                            "type" => "number"
                        ],
                        [
                            "type" => "bool"
                        ]
                    ]
                ]
            ]
        ];

        $definition = [
            123.456
        ];

        // checkType is called twice on a successful validation
        $this->typeValidator->shouldReceive("checkType")->andReturnValues([false, true, true, false]);

        $this->standardTest($schemas, $definition);
    }

    public function testOneOfValidationFailure()
    {
        $schemas = [
            "syringe" => [
                "element" => [
                    "oneOf" => [
                        [
                            "type" => "string"
                        ],
                        [
                            "type" => "number"
                        ],
                        [
                            "type" => "bool"
                        ]
                    ]
                ]
            ]
        ];

        $this->typeValidator->shouldReceive("checkType")->andReturnFalse();

        $definition = [
            ["foo"]
        ];

        $this->standardTest($schemas, $definition, 1);
    }

    public static function xorProvider(): array
    {
        return [
            [true, true, false],
            [true, false, true],
            [false, true, true],
            [false, false, false]
        ];
    }

    public static function typeProvider(): array
    {
        return [
            "serviceReference type success" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "serviceReference"
                        ]
                    ]
                ],
                [
                    "@ref"
                ],
                true
            ],
            "multiple types success" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => [
                                "string",
                                "number",
                                "bool"
                            ]
                        ]
                    ]
                ],
                [
                    123,
                    "foo",
                    true
                ],
                true,
                true
            ],
            "serviceReference type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "serviceReference"
                        ]
                    ]
                ],
                [
                    "12345"
                ],
                false
            ],
            "multiple types failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => [
                                "string",
                                "array",
                                "bool"
                            ]
                        ]
                    ]
                ],
                [
                    123
                ],
                false,
                false
            ]
        ];
    }

    public static function requiredChildrenSuccessProvider(): array
    {
        return [
            "single requirement" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => true
                        ]
                    ]
                ],
                [
                    "foo" => "bar"
                ]
            ],
            "multiple requirements" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => true,
                            "bar" => true,
                            "baz" => true
                        ]
                    ]
                ],
                [
                    "foo" => 1,
                    "bar" => 2,
                    "baz" => 3
                ]
            ],
            "required if (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "foo" => 1,
                    "bar" => 2
                ]
            ],
            "required if (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "foo" => 1,
                    "baz" => 2
                ]
            ],
            "not required if (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "baz" => 2
                ]
            ],
            "not required if (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "fuz" => 2
                ]
            ],
            "required if not (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "foo" => 1,
                    "baz" => 2
                ]
            ],
            "required if not (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "foo" => 1,
                    "fuz" => 2
                ]
            ],
            "not required if not (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "bar" => 2
                ]
            ],
            "not required if not (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "baz" => 2
                ]
            ]
        ];
    }

    public static function requiredChildrenFailureProvider(): array
    {
        return [
            "single requirement" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => true
                        ]
                    ]
                ],
                [
                    "bar" => "foo"
                ]
            ],
            "multiple requirements" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => true,
                            "bar" => true,
                            "baz" => true
                        ]
                    ]
                ],
                [
                    "fuz" => 1,
                    "bin" => 2,
                    "blah" => 3
                ],
                3
            ],
            "required if (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "bar" => "foo"
                ]
            ],
            "required if (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "if" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "baz" => "foo"
                ]
            ],
            "required if not (single)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => "bar"
                            ]
                        ]
                    ]
                ],
                [
                    "baz" => "foo"
                ]
            ],
            "required if not (multiple)" => [
                [
                    "syringe" => [
                        "requiredChildren" => [
                            "foo" => [
                                "ifNot" => [
                                    "bar",
                                    "baz"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "fuz" => "foo"
                ]
            ]
        ];
    }

}
