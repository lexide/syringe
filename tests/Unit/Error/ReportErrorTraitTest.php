<?php

namespace Lexide\Syringe\Test\Unit\Error;

use Lexide\Syringe\Error\ReportErrorsTrait;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Exception\ConfigException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ReportErrorTraitTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected LoggerInterface|MockInterface $logger;
    protected object $implementation;

    public function setUp(): void
    {
        $this->logger = \Mockery::mock(LoggerInterface::class);

        $this->implementation = new class ($this->logger) {
            use ReportErrorsTrait {
                reportErrors as public;
            }
            public function __construct(LoggerInterface $errorLogger)
            {
                $this->errorLogger = $errorLogger;
            }
        };
    }

    public function testNoErrors()
    {
        $this->logger->shouldNotReceive("log");

        $this->implementation->reportErrors([], false);
    }

    public function testAllWarningsAndSkip()
    {
        $this->logger->shouldNotReceive("log");
        $warnings = array_fill(0, 3, \Mockery::mock(SyringeError::class, ["getType" => "warning"]));

        $this->implementation->reportErrors($warnings, true);
    }

    #[DataProvider("errorProvider")]
    public function testLoggingErrors($errors, $expectedErrors, $skipWarnings = false)
    {
        $errorList = [];
        foreach ($errors as $error) {
            $mock = \Mockery::mock(SyringeError::class);
            $mock->shouldReceive("getType")->andReturn($error["type"] ?? "error");
            $mock->shouldReceive("getMessage")->andReturn($error["message"]);
            $mock->shouldReceive("getContext")->andReturn($error["context"] ?? []);
            $errorList[] = $mock;
        }

        foreach ($expectedErrors as $error) {
            $this->logger->shouldReceive("log")->with(...$error)->once();
        }

        $errorCount = count($expectedErrors);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/$errorCount validation errors/");

        $this->implementation->reportErrors($errorList, $skipWarnings);
    }

    public function testSingleErrorExceptionMessage()
    {
        $error = \Mockery::mock(SyringeError::class);
        $error->shouldReceive("getType")->andReturn("error");
        $error->shouldReceive("getMessage")->andReturn("foo");
        $error->shouldReceive("getContext")->andReturn(["bar" => "baz"]);

        $this->logger->shouldReceive("log")->with(LogLevel::ERROR, "foo", ["bar" => "baz"])->once();

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/Error: foo.*bar.*baz.*/");

        $this->implementation->reportErrors([$error], false);
    }

    public static function errorProvider(): array
    {
        $error = LogLevel::ERROR;
        $warning = LogLevel::WARNING;

        return [
            "all errors" => [
                [
                    ["type" => "error", "message" => "one", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "two", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "three", "context" => ["bar" => "baz"]]
                ],
                [
                    [$error, "one", ["bar" => "baz"]],
                    [$error, "two", ["bar" => "baz"]],
                    [$error, "three", ["bar" => "baz"]]
                ]
            ],
            "all warnings" => [
                [
                    ["type" => "warning", "message" => "one", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "two", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "three", "context" => ["bar" => "baz"]]
                ],
                [
                    [$warning, "one", ["bar" => "baz"]],
                    [$warning, "two", ["bar" => "baz"]],
                    [$warning, "three", ["bar" => "baz"]]
                ]
            ],
            "mixed" => [
                [
                    ["type" => "error", "message" => "one", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "two", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "three", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "four", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "five", "context" => ["bar" => "baz"]]
                ],
                [
                    [$error, "one", ["bar" => "baz"]],
                    [$warning, "two", ["bar" => "baz"]],
                    [$error, "three", ["bar" => "baz"]],
                    [$warning, "four", ["bar" => "baz"]],
                    [$error, "five", ["bar" => "baz"]]
                ]
            ],
            "mixed with warnings filtered" => [
                [
                    ["type" => "error", "message" => "one", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "two", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "three", "context" => ["bar" => "baz"]],
                    ["type" => "warning", "message" => "four", "context" => ["bar" => "baz"]],
                    ["type" => "error", "message" => "five", "context" => ["bar" => "baz"]]
                ],
                [
                    [$error, "one", ["bar" => "baz"]],
                    [$error, "three", ["bar" => "baz"]],
                    [$error, "five", ["bar" => "baz"]]
                ],
                true
            ]
        ];
    }

}
