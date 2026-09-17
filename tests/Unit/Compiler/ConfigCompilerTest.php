<?php

namespace Lexide\Syringe\Test\Unit\Compiler;

use Lexide\Syringe\Compiler\ConfigCompiler;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Normalisation\DefinitionsNormaliser;
use Lexide\Syringe\Validation\ReferenceValidator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigCompilerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected DefinitionsNormaliser|MockInterface $definitionsNormaliser;
    protected ReferenceValidator|MockInterface $referenceValidator;
    protected LoggerInterface|MockInterface $errorLogger;
    protected SyringeError|MockInterface $error;

    public function setUp(): void
    {
        $this->definitionsNormaliser = \Mockery::mock(DefinitionsNormaliser::class);
        $this->referenceValidator = \Mockery::mock(ReferenceValidator::class);
        $this->errorLogger = \Mockery::mock(LoggerInterface::class);
        $this->error = \Mockery::mock(SyringeError::class);
        $this->error->shouldIgnoreMissing("foo");
        $this->error->shouldReceive("getContext")->andReturn([]);
    }

    public function testNormalisationErrors()
    {
        $errorCount = 3;
        $errors = array_fill(0, $errorCount, $this->error);
        $this->errorLogger->shouldReceive("log")->times($errorCount);

        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([[], $errors]);
        $this->referenceValidator->shouldNotReceive("validate");

        $this->expectException(ConfigException::class);

        $compiler = $this->createCompiler();
        $compiler->compile([["file" => "blah", "namespace" => ""]]);
    }

    public function testReferenceErrors()
    {
        $errorCount = 4;
        $errors = array_fill(0, $errorCount, $this->error);
        $this->errorLogger->shouldReceive("log")->times($errorCount);

        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([[], []]);
        $this->referenceValidator->shouldReceive("validate")->andReturn($errors);

        $this->expectException(ConfigException::class);

        $compiler = $this->createCompiler();
        $compiler->compile([["file" => "blah", "namespace" => ""]]);
    }

    public function testIgnoringWarnings()
    {
        $this->error->shouldReceive("getType")->andReturn("warning");
        $this->errorLogger->shouldNotReceive("log");

        $errorCount = 4;
        $errors = array_fill(0, $errorCount, $this->error);
        $definitions = ["foo" => "bar"];

        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([$definitions, []]);
        $this->referenceValidator->shouldReceive("validate")->andReturn($errors);

        $compiler = $this->createCompiler();
        ["definitions" => $compiledDefinitions] = $compiler->compile(
            [["file" => "blah", "namespace" => ""]],
            true
        );

        $this->assertSame($definitions, $compiledDefinitions);
    }

    protected function createCompiler(): ConfigCompiler
    {
        return new ConfigCompiler(
            $this->definitionsNormaliser,
            $this->referenceValidator,
            $this->errorLogger
        );
    }

    public function loadDefinitionsProvider(): array
    {
        return [
            "single file" => [
                [["file" => "test.file", "namespace" => ""]],
                [
                    "test.file" => ["foo" => "bar"]
                ],
                [
                    "" => [
                        "foo" => "bar"
                    ]
                ]
            ],
            "multiple files, same namespace" => [
                [
                    ["file" => "test.one", "namespace" => ""],
                    ["file" => "test.two", "namespace" => ""],
                    ["file" => "test.three", "namespace" => ""]
                ],
                [
                    "test.one" => ["one" => "bar"],
                    "test.two" => ["two" => "bar"],
                    "test.three" => ["three" => "bar"]
                ],
                [
                    "" => [
                        "one" => "bar",
                        "two" => "bar",
                        "three" => "bar"
                    ]
                ],
            ],
            "multiple files, same namespace, overwrites (last definition wins)" => [
                [
                    ["file" => "test.one", "namespace" => ""],
                    ["file" => "test.two", "namespace" => ""],
                    ["file" => "test.three", "namespace" => ""]
                ],
                [
                    "test.one" => ["one" => "one", "two" => "one", "three" => "one"],
                    "test.two" => ["one" => "two", "two" => "two"],
                    "test.three" => ["one" => "three"]
                ],
                [
                    "" => [
                        "one" => "three",
                        "two" => "two",
                        "three" => "one"
                    ]
                ]
            ],
            "multiple files, multiple namespaces" => [
                [
                    ["file" => "test.one", "namespace" => "one"],
                    ["file" => "test.two", "namespace" => "two"],
                    ["file" => "test.three", "namespace" => "three"]
                ],
                [
                    "test.one" => ["one" => "one", "two" => "one", "three" => "one"],
                    "test.two" => ["one" => "two", "two" => "two"],
                    "test.three" => ["one" => "three"]
                ],
                [
                    "one" => [
                        "one" => "one",
                        "two" => "one",
                        "three" => "one"
                    ],
                    "two" => [
                        "one" => "two",
                        "two" => "two"
                    ],
                    "three" => [
                        "one" => "three"
                    ]
                ],
                ["one", "two", "three"]
            ],
            "imports" => [
                [
                    ["file" => "test.one", "namespace" => ""]
                ],
                [
                    "test.one" => [
                        "one" => "one",
                        "imports" => [
                            "test.two",
                            "test.three"
                        ]
                    ],
                    "test.one|test.two" => ["two" => "two"],
                    "test.one|test.three" => ["three" => "three"]
                ],
                [
                    "" => [
                        "one" => "one",
                        "two" => "two",
                        "three" => "three"
                    ]
                ]
            ],
            "imports, overwrites (first definition wins)" => [
                [
                    ["file" => "test.one", "namespace" => ""]
                ],
                [
                    "test.one" => [
                        "one" => "one",
                        "two" => "one",
                        "imports" => [
                            "test.two",
                            "test.three"
                        ]
                    ],
                    "test.one|test.two" => [
                        "two" => "two",
                        "three" => "two"
                    ],
                    "test.one|test.three" => ["three" => "three"]
                ],
                [
                    "" => [
                        "one" => "one",
                        "two" => "one",
                        "three" => "two"
                    ]
                ]
            ],
            "nested imports" => [
                [
                    ["file" => "test.one", "namespace" => ""]
                ],
                [
                    "test.one" => [
                        "one" => "one",
                        "imports" => [
                            "test.two",
                            "test.three"
                        ]
                    ],
                    "test.one|test.two" => [
                        "two" => "two",
                        "imports" => [
                            "test.four",
                            "test.five"
                        ]
                    ],
                    "test.one|test.three" => ["three" => "three"],
                    "test.two|test.four" => [
                        "four" => "four",
                        "imports" => [
                            "test.six"
                        ]
                    ],
                    "test.two|test.five" => ["five" => "five"],
                    "test.four|test.six" => ["six" => "six"],
                ],
                [
                    "" => [
                        "one" => "one",
                        "two" => "two",
                        "three" => "three",
                        "four" => "four",
                        "five" => "five",
                        "six" => "six"
                    ]
                ]
            ]
        ];
    }

    public function syntaxErrorProvider(): array
    {
        $standard = ["foo" => "bar"];

        return [
            "one error" => [
                [
                    "file.txt" => $standard
                ],
                [
                    ["file" => "file.txt", "namespace" => ""]
                ],
            ],
            "multiple files in error" => [
                [
                    "file1.txt" => $standard,
                    "file2.txt" => $standard
                ],
                [
                    ["file" => "file1.txt", "namespace" => ""],
                    ["file" => "file2.txt", "namespace" => ""]
                ],
            ],
            "imported files in error" => [
                [
                    "file1.txt" => ["imports" => ["file2.txt", "file3.txt"]],
                    "file2.txt" => $standard,
                    "file3.txt" => $standard
                ],
                [
                    ["file" => "file1.txt", "namespace" => ""]
                ],
            ],
            "imports and multiple files" => [
                [
                    "file1.txt" => ["imports" => ["file2.txt"]],
                    "file2.txt" => ["imports" => ["file3.txt"]],
                    "file3.txt" => $standard,
                    "file4.txt" => $standard
                ],
                [
                    ["file" => "file1.txt", "namespace" => ""],
                    ["file" => "file4.txt", "namespace" => ""]
                ],
            ],
        ];
    }

}
