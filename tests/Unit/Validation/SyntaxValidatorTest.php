<?php

namespace Lexide\Syringe\Test\Unit\Validation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SyntaxValidatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var CompilationHelper|MockInterface
     */
    protected $helper;

    public function setUp(): void
    {
        $this->helper = \Mockery::mock(CompilationHelper::class);
        $this->helper->shouldReceive("isServiceReference")->passthru();
        $this->helper->shouldIgnoreMissing(\Mockery::mock(ValidationError::class));
    }

    protected function standardTest(array $schemas, array $definition, int $errorCount = 0)
    {
        $validator = new SyntaxValidator($this->helper, $schemas);

        $this->assertCount($errorCount, $validator->validateFile($definition, "test.yml"));
    }

    /**
     * @dataProvider typeSuccessProvider
     * @param array $schemas
     * @param array $definition
     */
    public function testTypeValidationSuccessful(array $schemas, array $definition)
    {
        $this->standardTest($schemas, $definition);
    }

    /**
     * @dataProvider typeFailureProvider
     * @param array $schemas
     * @param array $definition
     * @param int $errorCount
     */
    public function testTypeValidationFailure(array $schemas, array $definition, int $errorCount = 1)
    {
        $this->standardTest($schemas, $definition, $errorCount);
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

        $this->standardTest($schemas, $definition, 3);
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

        $this->standardTest($schemas, $definition, 5);
    }

    /**
     * @dataProvider requiredChildrenSuccessProvider
     * @param array $schemas
     * @param array $definition
     */
    public function testRequiredChildrenValidationSuccess(array $schemas, array $definition)
    {
        $this->standardTest($schemas, $definition);
    }

    /**
     * @dataProvider requiredChildrenFailureProvider
     * @param array $schemas
     * @param array $definition
     * @param int $errorCount
     */
    public function testRequiredChildrenValidationFailure(array $schemas, array $definition, int $errorCount = 1)
    {
        $this->standardTest($schemas, $definition, $errorCount);
    }

    /**
     * @dataProvider xorProvider
     *
     * @param bool $shouldBeEmpty
     * @param bool $isEmpty
     * @param bool $errorExpected
     */
    public function testEmptyValidation(bool $shouldBeEmpty, bool $isEmpty, bool $errorExpected)
    {

        $schemas = [
            "syringe" => [
                "empty" => $shouldBeEmpty
            ]
        ];

        $definition = $isEmpty? []: ["not empty"];

        $validator = new SyntaxValidator($this->helper, $schemas);

        $errors = $validator->validateFile($definition, "test.yml");

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

        $this->helper->shouldReceive("warning")->once()->andReturn(\Mockery::mock(ValidationError::class));

        $validator = new SyntaxValidator($this->helper, $schemas);
        $errors = $validator->validateFile($definition, "test.yml");

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

        $definition = [
            ["foo"]
        ];

        $this->standardTest($schemas, $definition, 1);
    }

    public function twoProvider(): array
    {
        return [
            [[], []]
        ];
    }

    public function threeProvider(): array
    {
        return [
            [[], [], 1]
        ];
    }

    /**
     * @return array
     */
    public function xorProvider(): array
    {
        return [
            [true, true, false],
            [true, false, true],
            [false, true, true],
            [false, false, false]
        ];
    }

    public function typeSuccessProvider()
    {
        return [
            "array type" => [
                [
                    "syringe" => [
                        "type" => "array"
                    ]
                ],
                [
                    "one",
                    "two" => 2
                ]
            ],
            "list type" => [
                [
                    "syringe" => [
                        "type" => "list"
                    ]
                ],
                [
                    "one",
                    "two"
                ]
            ],
            "object type" => [
                [
                    "syringe" => [
                        "type" => "object"
                    ]
                ],
                [
                    "one" => 1,
                    "two" => 2
                ]
            ],
            "string type" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "string"
                        ]
                    ]
                ],
                [
                    "string"
                ]
            ],
            "number type (integer)" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "number"
                        ]
                    ]
                ],
                [
                    123
                ]
            ],
            "number type (float)" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "number"
                        ]
                    ]
                ],
                [
                    0.123
                ]
            ],
            "bool type" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "bool"
                        ]
                    ]
                ],
                [
                    true
                ]
            ],
            "serviceReference type" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "serviceReference"
                        ]
                    ]
                ],
                [
                    "@ref"
                ]
            ],
            "any type (object)" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "any"
                        ]
                    ]
                ],
                [
                    new \stdClass()
                ]
            ],
            "any type (scalar)" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "any"
                        ]
                    ]
                ],
                [
                    12345
                ]
            ],
            "any type (array)" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "any"
                        ]
                    ]
                ],
                [
                    ["foo"]
                ]
            ],
            "multiple allowed types" => [
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
                ]
            ]
        ];
    }

    public function typeFailureProvider(): array
    {
        return [
            "string type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "string"
                        ]
                    ]
                ],
                [
                    12345
                ]
            ],
            "number type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "number"
                        ]
                    ]
                ],
                [
                    "12345"
                ]
            ],
            "bool type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "bool"
                        ]
                    ]
                ],
                [
                    12345
                ]
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
                ]
            ],
            "array type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "array"
                        ]
                    ]
                ],
                [
                    "12345"
                ]
            ],
            "list type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "list"
                        ]
                    ]
                ],
                [
                    [
                        "one" => 1,
                        "two" => 2
                    ]
                ]
            ],
            "object type failure" => [
                [
                    "syringe" => [
                        "element" => [
                            "type" => "object"
                        ]
                    ]
                ],
                [
                    [
                        "one",
                        "two"
                    ]
                ]
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
                ]
            ]
        ];
    }

    public function requiredChildrenSuccessProvider()
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

    public function requiredChildrenFailureProvider()
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
