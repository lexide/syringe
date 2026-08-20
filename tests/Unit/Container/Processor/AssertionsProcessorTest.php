<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\AssertionRegistry;
use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Container\Processor\AssertionsProcessor;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Exception\ConfigException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Psr\Log\LoggerInterface;

class AssertionsProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected AssertionRegistry|MockInterface $registry;
    protected AssertionInterface|MockInterface $assertion;
    protected Container|MockInterface $container;
    protected ContainerOptions|MockInterface $options;
    protected SyringeError|MockInterface $error;
    protected LoggerInterface|MockInterface $logger;

    public function setUp(): void
    {
        $this->assertion = \Mockery::mock(AssertionInterface::class);
        $this->registry = \Mockery::mock(AssertionRegistry::class);


        $this->container = \Mockery::mock(Container::class);

        $this->error = \Mockery::mock(SyringeError::class);
        $this->error->shouldIgnoreMissing("");
        $this->error->shouldReceive("getContext")->andReturn([]);
        $this->logger = \Mockery::mock(LoggerInterface::class);

        $this->options = \Mockery::mock(ContainerOptions::class);
        $this->options->shouldReceive("processAssertions")->andReturnTrue();
        $this->options->shouldReceive("ignoreAssertionWarnings")->andReturnFalse();
    }

    #[DataProvider("typeProvider")]
    public function testIdentifyingType(string $definitionSection, ?string $expectedType)
    {
        $reference = "foo";
        $definition = ["bar" => "baz"];
        $definitions = [
            $definitionSection => [
                $reference => $definition
            ],
            "assertions" => [
                [
                    "reference" => $reference,
                    "checks" => [
                        [
                            "assert" => "blah"
                        ]
                    ]
                ]
            ]
        ];

        $expectedDefinition = in_array($expectedType, [AssertionInterface::TYPE_SERVICE, AssertionInterface::TYPE_PARAMETER])
            ? $definition
            : null;

        $this->options->shouldReceive("errorLogger")->andReturnNull();

        $this->registry->shouldReceive("getAssertion")->andReturn($this->assertion);
        $this->assertion->shouldReceive("assert")
            ->with(\Mockery::any(), $reference, $expectedType, $expectedDefinition, $this->container)
            ->once()
            ->andReturn([]);

        $processor = new AssertionsProcessor($this->registry);
        $processor->process($definitions, $this->container, $this->options);
    }

    public function testAssertions()
    {

        $types = [
            "foo" => AssertionInterface::TYPE_PARAMETER,
            "bar" => AssertionInterface::TYPE_SERVICE
        ];

        $values = [
            "foo" => "one",
            "bar" => "two"
        ];

        $assertions = [
            "foo" => [
                "assertOne" => null
            ],
            "bar" => [
                "assertTwoOne" => null,
                "assertTwoTwo" => "baz"
            ],
            "fiz" => [
                "assertThree" => null
            ]
        ];

        $errors = [$this->error];

        $definitions = [
            "parameters" => [],
            "services" => [],
            "assertions" => []
        ];

        $expectedErrorCount = 0;

        foreach ($assertions as $reference => $checks) {
            $type = $types[$reference] ?? null;
            $value = $values[$reference] ?? null;
            foreach ($checks as $assert => $operand) {
                $this->registry->shouldReceive("getAssertion")->with($assert)->once()->andReturn($this->assertion);
                $this->assertion->shouldReceive("assert")
                    ->with($operand, $reference, $type, $value, $this->container)
                    ->once()
                    ->andReturn($errors);
                ++$expectedErrorCount;
            }

            if (!empty($type)) {
                $definitions[$type . "s"][$reference] = $value;
            }
            $definitions["assertions"][] = [
                "reference" => $reference,
                "checks" => array_map(
                    function($assert) use ($checks) {
                        $definition = [
                            "assert" => $assert
                        ];
                        if (!empty($checks[$assert])) {
                            $definition["operand"] = $checks[$assert];
                        }
                        return $definition;
                    },
                    array_keys($checks)
                )
            ];
        }

        $this->options->shouldReceive("errorLogger")->andReturn($this->logger);
        $this->logger->shouldReceive("log")->times($expectedErrorCount);

        $this->expectException(ConfigException::class);

        $processor = new AssertionsProcessor($this->registry);
        $processor->process($definitions, $this->container, $this->options);
    }

    public static function typeProvider(): array
    {
        return [
            "parameter" => [
                "parameters",
                AssertionInterface::TYPE_PARAMETER
            ],
            "service" => [
                "services",
                AssertionInterface::TYPE_SERVICE
            ],
            "other" => [
                "extensions",
                null
            ]
        ];
    }

}
