<?php

namespace Lexide\Syringe\Test\Unit\Compiler;

use Lexide\Syringe\Compiler\ConfigLoader;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Loader\LoaderInterface;
use Lexide\Syringe\Loader\LoaderRegistry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class ConfigLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var LoaderInterface|MockInterface
     */
    protected $loader;

    /**
     * @var LoaderRegistry|MockInterface
     */
    protected $loaderRegistry;

    /**
     * @var vfsStreamDirectory
     */
    protected $vfs;

    public function setUp(): void
    {
        $this->loader = \Mockery::mock(LoaderInterface::class);
        $this->loaderRegistry = \Mockery::mock(LoaderRegistry::class);
        $this->loaderRegistry->shouldReceive("findLoaderForFile")->andReturn($this->loader);
    }

    /**
     * @dataProvider fileSystemProvider
     *
     * @param array $fileSystem
     * @param array $configPaths
     * @param string $file
     * @param string $relativeTo
     * @param bool $expectedToFind
     */
    public function testFindingFiles(
        array $fileSystem,
        array $configPaths,
        string $file,
        ?string $expectedFilePath = "", // set to null for "not found"
        string $relativeTo = ""
    ) {
        $vfs = vfsStream::setup("test", 0777, $fileSystem);
        if (!is_null($expectedFilePath)) {
            $this->loader->shouldReceive("loadFile")->with(\Mockery::pattern("|.*$file\$|"))->once();
            if (empty($expectedFilePath)) {
                $expectedFilePath = $file;
            }
        } else {
            $this->expectException(ConfigException::class);
        }

        if (!empty($relativeTo)) {
            $relativeTo = $vfs->url() . "/" . $relativeTo;
        }

        $configLoader = new ConfigLoader($this->loaderRegistry);

        foreach ($configPaths as $i => $path) {
            $configPaths[$i] = $vfs->url() . "/" . $path;
        }
        $configLoader->setConfigPaths($configPaths);

        [$content, $filePath] = $configLoader->loadConfig($file, $relativeTo);

        $this->assertStringContainsString($expectedFilePath, $filePath);
    }

    public function fileSystemProvider(): array
    {
        return [
            "single config dir - found" => [
                [
                    "config" => [
                        "testFile.txt" => "blah"
                    ]
                ],
                ["config"],
                "testFile.txt"
            ],
            "single config dir - not found" => [
                [
                    "config" => [
                        "testFile.txt" => "blah"
                    ]
                ],
                ["config"],
                "missingFile.txt",
                null
            ],
            "multiple config dirs - found" => [
                [
                    "config1" => [
                        "foo.txt" => "blah"
                    ],
                    "config2" => [
                        "bar.txt" => "blah"
                    ],
                    "config3" => [
                        "testFile.txt" => "blah"
                    ]
                ],
                ["config1", "config2", "config3"],
                "testFile.txt"
            ],
            "multiple config dirs - not found" => [
                [
                    "config1" => [
                        "foo.txt" => "blah"
                    ],
                    "config2" => [
                        "bar.txt" => "blah"
                    ],
                    "config3" => [
                        "baz.txt" => "blah"
                    ]
                ],
                ["config1", "config2", "config3"],
                "testFile.txt",
                null
            ],
            "multiple config dirs - duplicate files" => [
                [
                    "config1" => [
                        "foo.txt" => "blah"
                    ],
                    "config2" => [
                        "testFile.txt" => "blah"
                    ],
                    "config3" => [
                        "testFile.txt" => "blah"
                    ]
                ],
                ["config1", "config2", "config3"],
                "testFile.txt",
                "config2/testFile.txt"
            ],
            "deep config dir - found" => [
                [
                    "level1" => [
                        "level2" => [
                            "level3" => [
                                "testFile.txt" => "blah"
                            ]
                        ]
                    ]
                ],
                ["level1/level2/level3"],
                "testFile.txt"
            ],
            "deep filepath - found" => [
                [
                    "level1" => [
                        "level2" => [
                            "level3" => [
                                "testFile.txt" => "blah"
                            ]
                        ]
                    ]
                ],
                ["level1"],
                "level2/level3/testFile.txt"
            ],
            "relative search" => [
                [
                    "level1" => [
                        "level2" => [
                            "relativeFile.txt" => "blah",
                            "testFile.txt" => "blah"
                        ]
                    ]
                ],
                ["level1"],
                "testFile.txt",
                "level2/testFile.txt",
                "level1/level2/relativeFile.txt"
            ],
            "deep relative search" => [
                [
                    "level1" => [
                        "relativeFile.txt" => "blah",
                        "level2" => [
                            "testFile.txt" => "blah"
                        ]
                    ]
                ],
                ["level1"],
                "level2/testFile.txt",
                "",
                "level1/relativeFile.txt"
            ],
            "relative search - only same level" => [
                [
                    "level1" => [
                        "relativeFile.txt" => "blah",
                        "level2" => [
                            "testFile.txt" => "blah"
                        ]
                    ]
                ],
                ["level1"],
                "testFile.txt",
                null,
                "level1/relativeFile.txt"
            ],
            "relative search - use normal search if not found" => [
                [
                    "config1" => [
                        "relativeFile.txt" => "blah",
                    ],
                    "config2" => [
                        "testFile.txt" => "blah"
                    ]
                ],
                ["config1", "config2"],
                "testFile.txt",
                "config2/testFile.txt",
                "level1/relativeFile.txt"
            ],
            "relative search - no parent directories" => [
                [
                    "level1" => [
                        "testFile.txt" => "blah",
                        "level2" => [
                            "relativeFile.txt" => "blah"
                        ]
                    ]
                ],
                ["level1"],
                "../testFile.txt",
                null,
                "level1/level2/relativeFile.txt"
            ],
            "relative search - no complex parent directories" => [
                [
                    "level1" => [
                        "testFile.txt" => "blah",
                        "level2" => [
                            "relativeFile.txt" => "blah"
                        ],
                        "foo" => [
                            "bar" => []
                        ]
                    ]
                ],
                ["level1"],
                "../foo/bar/../../testFile.txt",
                null,
                "level1/level2/relativeFile.txt"
            ],
        ];
    }

}
