<?php

namespace Lexide\Syringe\Test\Unit\Compiler;

use Lexide\Syringe\Compiler\ConfigLoader;
use Lexide\Syringe\Compiler\DefinitionLoader;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Provider\DefinitionProviderInterface;
use Lexide\Syringe\Validation\SyntaxValidator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DefinitionLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected ConfigLoader|MockInterface $configLoader;
    protected SyntaxValidator|MockInterface $syntaxValidator;
    protected DefinitionProviderInterface $definitionProvider;

    public function setUp(): void
    {
        $this->configLoader = \Mockery::mock(ConfigLoader::class);
        $this->syntaxValidator = \Mockery::mock(SyntaxValidator::class);
        $this->definitionProvider = \Mockery::mock(DefinitionProviderInterface::class);
    }

    #[DataProvider("definitionsProvider")]
    public function testLoadingDefinitions(array $configFiles, array $definitions, array $expected, ?array $validatedFiles = null)
    {
        if (empty($validatedFiles)) {
            $validatedFiles = array_map(fn($fileConfig) => $fileConfig["file"], $configFiles);
        }
        $validatedFiles = array_flip($validatedFiles);

        $this->syntaxValidator->shouldReceive("validate")->andReturnUsing(function($d, $file) use (&$validatedFiles) {
            $this->assertArrayHasKey($file, $validatedFiles);
            unset($validatedFiles[$file]);
            return [];
        });

        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(fn ($file) => $definitions[$file]);

        $definitionLoader = new DefinitionLoader($this->configLoader, $this->syntaxValidator);
        $actual = $definitionLoader->loadDefinitions($configFiles, []);
        $this->assertSame($expected, $actual);

        $this->assertEmpty($validatedFiles, "Did not validate all files. Missing " . implode(", ", array_keys($validatedFiles)));
    }

    public function testErrorOnCircularImport()
    {
        $this->expectException(ConfigException::class);

        $definitions = [
            "foo" => ["imports" => ["bar"], "one" => "foo"],
            "bar" => ["imports" => ["foo"], "two" => "bar"]
        ];

        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(fn ($file) => [$definitions[$file], $file]);

        $definitionLoader = new DefinitionLoader($this->configLoader, $this->syntaxValidator);
        $definitionLoader->loadDefinitions([["namespace" => "", "file" => "foo"]], [], true);
    }

    public function testCollectingSyntaxErrors()
    {
        $e = 0;
        $error = \Mockery::mock(SyringeError::class);
        $this->syntaxValidator->shouldReceive("validate")->andReturnUsing(function () use (&$e, $error) {
            return array_fill(0, ++$e, $error);
        });

        $definitions = [
            "one" => ["imports" => ["two"], "one" => "foo"],
            "two" => ["two" => "bar"],
            "three" => ["imports" => ["four"], "three" => "baz"],
            "four" => ["four" => "fiz"],
            "five" => ["five" => "blah"],
        ];
        $configFiles = [
            ["namespace" => "", "file" => "one"],
            ["namespace" => "", "file" => "three"],
            ["namespace" => "", "file" => "five"],
        ];

        $totalFileCount = count($definitions);

        $errorCount = ($totalFileCount * ($totalFileCount + 1)) / 2;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/ $errorCount validation errors/");

        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(fn ($file) => [$definitions[$file], $file]);

        $definitionLoader = new DefinitionLoader($this->configLoader, $this->syntaxValidator);
        $definitionLoader->loadDefinitions($configFiles, []);
    }

    #[DataProvider("providerProvider")]
    public function testProviders(array $providerDefinitions, array $expected)
    {
        $providers = [];
        foreach ($providerDefinitions as $name => $config) {
            $provider = \Mockery::mock(DefinitionProviderInterface::class);
            $provider->shouldReceive("getName")->andReturn($name);
            $provider->shouldReceive("getNamespace")->andReturn($config["namespace"]);
            $provider->shouldReceive("getDefinitions")->andReturn($config["definitions"]);
            $this->syntaxValidator->shouldReceive("validate")->with(\Mockery::any(), $name)->once()->andReturn([]);
            $providers[] = $provider;
        }

        $definitionLoader = new DefinitionLoader($this->configLoader, $this->syntaxValidator);
        $actual = $definitionLoader->loadDefinitions([], $providers);
        $this->assertSame($expected, $actual);
    }

    public function testSkippingValidation()
    {
        $this->syntaxValidator->shouldNotReceive("validate");
        $configFiles = [
            ["namespace" => "foo", "file" => "foo.yml"],
            ["namespace" => "bar", "file" => "bar.yml"],
            ["namespace" => "baz", "file" => "baz.yml"]
        ];

        $this->configLoader->shouldReceive("loadConfig")->andReturnUsing(fn ($file) => [["fiz" => true], $file]);

        $definitionLoader = new DefinitionLoader($this->configLoader, $this->syntaxValidator);
        $actual = $definitionLoader->loadDefinitions($configFiles, [], true);
        $expected = [
            "foo" => ["fiz" => true],
            "bar" => ["fiz" => true],
            "baz" => ["fiz" => true]
        ];

        $this->assertSame($expected, $actual);

    }

    public static function definitionsProvider(): array
    {
        return [
            "single file" => [
                [["namespace" => "", "file" => "foo"]],
                ["foo" => [["bar" => "baz"], "foo"]],
                ["" => ["bar" => "baz"]]
            ],
            "multiple files, different namespaces" => [
                [
                    ["namespace" => "one", "file" => "foo"],
                    ["namespace" => "two", "file" => "bar"],
                    ["namespace" => "three", "file" => "baz"]
                ],
                [
                    "foo" => [["bar" => "baz"], "foo"],
                    "bar" => [["bar" => "baz"], "bar"],
                    "baz" => [["bar" => "baz"], "baz"]
                ],
                [
                    "one" => ["bar" => "baz"],
                    "two" => ["bar" => "baz"],
                    "three" => ["bar" => "baz"]
                ]
            ],
            "multiple files, namespaces merge (last wins)" => [
                [
                    ["namespace" => "one", "file" => "foo"],
                    ["namespace" => "one", "file" => "bar"],
                    ["namespace" => "one", "file" => "baz"]
                ],
                [
                    "foo" => [["one" => "foo"], "foo"],
                    "bar" => [["two" => "bar"], "bar"],
                    "baz" => [["one" => "baz"], "baz"]
                ],
                [
                    "one" => ["one" => "baz", "two" => "bar"]
                ]
            ],
            "simple import" => [
                [
                    ["namespace" => "one", "file" => "foo"]
                ],
                [
                    "foo" => [["imports" => ["bar"], "one" => "foo"], "foo"],
                    "bar" => [["two" => "bar"], "bar"],
                ],
                [
                    "one" => ["two" => "bar", "one" => "foo"]
                ],
                ["foo", "bar"]
            ],
            "single import, parent wins" => [
                [
                    ["namespace" => "one", "file" => "foo"]
                ],
                [
                    "foo" => [["imports" => ["bar"], "one" => "foo"], "foo"],
                    "bar" => [["one" => "bar", "two" => "baz"], "bar"],
                ],
                [
                    "one" => ["one" => "foo", "two" => "baz"]
                ],
                ["foo", "bar"]
            ],
            "multiple import, last wins" => [
                [
                    ["namespace" => "one", "file" => "foo"]
                ],
                [
                    "foo" => [["imports" => ["bar", "baz", "fiz"], "one" => "foo"], "foo"],
                    "bar" => [["two" => "bar"], "bar"],
                    "baz" => [["two" => "baz"], "baz"],
                    "fiz" => [["three" => "fiz"], "fiz"],
                ],
                [
                    "one" => ["two" => "baz", "three" => "fiz", "one" => "foo"]
                ],
                ["foo", "bar", "baz", "fiz"]
            ],
            "nested imports" => [
                [
                    ["namespace" => "one", "file" => "foo"]
                ],
                [
                    "foo" => [["imports" => ["bar"], "one" => "foo"], "foo"],
                    "bar" => [["imports" => ["baz"], "two" => "bar"], "bar"],
                    "baz" => [["imports" => ["fiz"], "three" => "baz"], "baz"],
                    "fiz" => [["four" => "fiz"], "fiz"],
                ],
                [
                    "one" => ["four" => "fiz", "three" => "baz", "two" => "bar", "one" => "foo"]
                ],
                ["foo", "bar", "baz", "fiz"]
            ],
            "extensions and assertions are merged, not replaced" => [
                [
                    ["namespace" => "one", "file" => "foo"],
                    ["namespace" => "one", "file" => "bar"],
                ],
                [
                    "foo" => [[
                        "extensions" => ["foo" => ["one", "two"]],
                        "assertions" => ["one", "two"],
                        "bar" => ["one", "two"]
                    ], "foo"],
                    "bar" => [[
                        "extensions" => ["foo" => ["three", "four"]],
                        "assertions" => ["three", "four"],
                        "bar" => ["three", "four"]
                    ], "bar"]
                ],
                [
                    "one" => [
                        "extensions" => ["foo" => ["one", "two", "three", "four"]],
                        "assertions" => ["one", "two", "three", "four"],
                        "bar" => ["three", "four"] // replaced, not merged
                    ]
                ],
                ["foo", "bar"]
            ]
        ];
    }

    public static function providerProvider(): array
    {
        return [
            "single provider" => [
                ["foo" => ["namespace" => "foo", "definitions" => ["bar" => "baz"]]],
                ["foo" => ["bar" => "baz"]]
            ],
            "multiple providers, different namespaces" => [
                [
                    "one" => ["namespace" => "foo", "definitions" => ["one" => "two"]],
                    "two" => ["namespace" => "bar", "definitions" => ["one" => "two"]],
                    "three" => ["namespace" => "baz", "definitions" => ["one" => "two"]]
                ],
                [
                    "foo" => ["one" => "two"],
                    "bar" => ["one" => "two"],
                    "baz" => ["one" => "two"]
                ]
            ],
            "multiple providers, namespaces merge (last wins)" => [
                [
                    "one" => ["namespace" => "foo", "definitions" => ["one" => "foo"]],
                    "two" => ["namespace" => "foo", "definitions" => ["two" => "bar"]],
                    "three" => ["namespace" => "foo", "definitions" => ["one" => "baz"]]
                ],
                [
                    "foo" => ["one" => "baz", "two" => "bar"]
                ]
            ],
            "provider imports are ignored" => [
                [
                    "one" => ["namespace" => "foo", "definitions" => ["imports" => ["one", "two", "three"], "bar" => "baz"]]
                ],
                ["foo" => ["bar" => "baz"]]
            ]
        ];
    }

}
