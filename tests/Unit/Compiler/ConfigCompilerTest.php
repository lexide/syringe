<?php

namespace Lexide\Syringe\Test\Unit\Compiler;

use Lexide\Syringe\Compiler\ConfigCompiler;
use Lexide\Syringe\Compiler\ConfigLoader;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Normalisation\DefinitionsNormaliser;
use Lexide\Syringe\Validation\ReferenceValidator;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigCompilerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var ConfigLoader|MockInterface
     */
    protected $configLoader;

    /**
     * @var SyntaxValidator|MockInterface
     */
    protected $syntaxValidator;

    /**
     * @var DefinitionsNormaliser|MockInterface
     */
    protected $definitionsNormaliser;

    /**
     * @var ReferenceValidator|MockInterface
     */
    protected $referenceValidator;

    /**
     * @var LoggerInterface|MockInterface
     */
    protected $errorLogger;

    /**
     * @var ValidationError|MockInterface
     */
    protected $error;

    public function setUp(): void
    {
        $this->configLoader = \Mockery::mock(ConfigLoader::class);
        $this->syntaxValidator = \Mockery::mock(SyntaxValidator::class);
        $this->definitionsNormaliser = \Mockery::mock(DefinitionsNormaliser::class);
        $this->referenceValidator = \Mockery::mock(ReferenceValidator::class);
        $this->errorLogger = \Mockery::mock(LoggerInterface::class);
        $this->error = \Mockery::mock(ValidationError::class);
        $this->error->shouldIgnoreMissing("foo");
        $this->error->shouldReceive("getContext")->andReturn([]);
    }

    /**
     * @dataProvider loadDefinitionsProvider
     *
     * @param array $files
     * @param array $definitions
     * @param array $expectedDefinitions
     * @param array|string[] $expectedNamespaces
     */
    public function testLoadingDefinitions(array $files, array $definitions, array $expectedDefinitions, array $expectedNamespaces = [""])
    {
        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(function ($file, $relativeTo = "") use (&$definitions) {
            $definitionKey = empty($relativeTo)? $file: "$relativeTo|$file";
            $this->assertArrayHasKey($definitionKey, $definitions, "No definitions were found for the requested file '$file', relative to '$relativeTo'");
            $return = $definitions[$definitionKey];
            unset($definitions[$definitionKey]);
            return [$return, $file];
        });

        $this->syntaxValidator->shouldReceive("validateFile")->andReturn([]);
        $this->definitionsNormaliser->shouldReceive("normalise")->andReturnUsing(function ($definitions) {return [$definitions, []];});
        $this->referenceValidator->shouldReceive("validate")->andReturn([]);

        $compiler = $this->createCompiler();

        ["definitions" => $result, "namespaces" => $namespaces] = $compiler->compile($files, []);

        $this->assertEquals($expectedNamespaces, $namespaces);

        foreach ($expectedDefinitions as $namespace => $expectedDefinition) {
            $this->assertArrayHasKey($namespace, $result);
            $this->assertEquals($result[$namespace], $expectedDefinition);
            unset($result[$namespace]);
        }

        $this->assertEmpty($result, "More definitions were found than were expected");
    }

    /**
     * @dataProvider syntaxErrorProvider
     *
     * @param array $definitions
     * @param array $configFiles
     * @throws ConfigException
     * @throws \Lexide\Syringe\Exception\LoaderException
     * @throws \Lexide\Syringe\Exception\ReferenceException
     */
    public function testSyntaxErrors(array $definitions, array $configFiles)
    {
        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(function ($file) use (&$definitions) {
            $this->assertArrayHasKey($file, $definitions, "No definitions were found for the requested file '$file'");
            $return = $definitions[$file];
            return [$return, $file];
        });

        $totalErrors = 0;
        $validationResults = [];
        $definitionsCount = count($definitions);
        for ($i = 1; $i <= $definitionsCount; ++$i) {
            $errors = array_fill(0, $i, $this->error);
            $validationResults[] = $errors;
            $totalErrors += $i;
        }
        $this->syntaxValidator->shouldReceive("validateFile")->andReturnValues($validationResults);
        $this->definitionsNormaliser->shouldNotReceive("normalise");
        $this->referenceValidator->shouldNotReceive("validate");

        $this->errorLogger->shouldReceive("log")->times($totalErrors);

        $this->expectException(ConfigException::class);

        $compiler = $this->createCompiler();

        $compiler->compile($configFiles);

    }

    public function testNormalisationErrors()
    {
        $this->configLoader->shouldReceive("loadConfig")->andReturn([["foo" => "bar"], "baz"]);

        $errorCount = 3;
        $errors = array_fill(0, $errorCount, $this->error);
        $this->errorLogger->shouldReceive("log")->times($errorCount);

        $this->syntaxValidator->shouldReceive("validateFile")->andReturn([]);
        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([[], $errors]);
        $this->referenceValidator->shouldNotReceive("validate");

        $this->expectException(ConfigException::class);

        $compiler = $this->createCompiler();
        $compiler->compile([["file" => "blah", "namespace" => ""]]);
    }

    public function testReferenceErrors()
    {
        $this->configLoader->shouldReceive("loadConfig")->andReturn([["foo" => "bar"], "baz"]);

        $errorCount = 4;
        $errors = array_fill(0, $errorCount, $this->error);
        $this->errorLogger->shouldReceive("log")->times($errorCount);

        $this->syntaxValidator->shouldReceive("validateFile")->andReturn([]);
        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([[], []]);
        $this->referenceValidator->shouldReceive("validate")->andReturn($errors);

        $this->expectException(ConfigException::class);

        $compiler = $this->createCompiler();
        $compiler->compile([["file" => "blah", "namespace" => ""]]);
    }

    public function testIgnoringWarnings()
    {

        $this->configLoader->shouldReceive("loadConfig")->andReturn([["foo" => "bar"], "baz"]);

        $options = ["ignoreWarnings" => true];
        $this->error->shouldReceive("getType")->andReturn("warning");
        $this->errorLogger->shouldNotReceive("log");

        $errorCount = 4;
        $errors = array_fill(0, $errorCount, $this->error);
        $definitions = ["foo" => "bar"];

        $this->syntaxValidator->shouldReceive("validateFile")->andReturn([]);
        $this->definitionsNormaliser->shouldReceive("normalise")->andReturn([$definitions, []]);
        $this->referenceValidator->shouldReceive("validate")->andReturn($errors);

        $compiler = $this->createCompiler();
        ["definitions" => $compiledDefinitions] = $compiler->compile(
            [["file" => "blah", "namespace" => ""]],
            $options
        );

        $this->assertSame($definitions, $compiledDefinitions);
    }

    protected function createCompiler(): ConfigCompiler
    {
        return new ConfigCompiler(
            $this->configLoader,
            $this->syntaxValidator,
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
