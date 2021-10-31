<?php

namespace Lexide\Syringe\Test\Unit\Validation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\Validation\ReferenceValidatorHelper;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ReferenceValidatorHelperTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    const TEST = 'test';

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
        $this->helper = \Mockery::mock(CompilationHelper::class);
        $this->error = \Mockery::mock(ValidationError::class);
    }

    /**
     * @dataProvider parametersProvider
     *
     * @param array $definedParameters
     * @param array $foundParameters
     * @param array $expectedErrors
     * @param array $expectedReferences
     * @param bool $skipParameters
     */
    public function testParameterReferences(
        array $definedParameters,
        array $foundParameters,
        array $expectedErrors,
        array $expectedReferences,
        bool $skipParameters = false
    ) {

        $errorCount = count($expectedErrors);

        foreach ($foundParameters as $parameter) {
            $this->helper
                ->shouldReceive("replaceParameterReference")
                ->with(\Mockery::any(), $parameter, '', true)
                ->times($skipParameters? 0: 1)
                ->andReturnArg(0);
        }

        $foundParameters[] = null;
        $this->helper->shouldReceive("findNextParameter")->andReturnValues($foundParameters);

        $this->helper->shouldReceive("referenceError")->andReturnUsing(function ($message) use (&$expectedErrors) {
            foreach ($expectedErrors as $i => $expectedError) {
                if (preg_match($expectedError, $message)) {
                    unset($expectedErrors[$i]);
                    return $this->error;
                }
            }
            $this->fail("The validation error message '$message' was not expected (could it have been raised twice?)");
        });

        $helper = new ReferenceValidatorHelper($this->helper);
        $helper->setDefinitions(["parameters" => $definedParameters]);

        [$errors, $references] = $helper->checkParameterReferences("anything", ["skipParameters" => $skipParameters]);

        $this->assertCount($errorCount, $errors);
        $this->assertEmpty($expectedErrors, "There were expected errors that were not raised");

        $this->assertCount(count($expectedReferences), $references);

        foreach ($expectedReferences as $expectedReference) {
            foreach ($references as $reference) {
                if ($reference == $expectedReference) {
                    // found
                    continue 2;
                }
            }
            $this->fail("No reference matching '$expectedReference' was found");
        }
    }

    public function testMaxParameterReferences()
    {
        $max = 5;

        $this->expectException(\LogicException::class);
        $this->helper->shouldReceive("findNextParameter")->times($max + 1)->andReturn("foo");
        $this->helper->shouldReceive("replaceParameterReference")->andReturnArg(0);

        $helper = new ReferenceValidatorHelper($this->helper, $max);
        $helper->setDefinitions(["parameters" => ["foo" => "bar"]]);

        $helper->checkParameterReferences("foo");
    }

    /**
     * @dataProvider constantProvider
     *
     * @param array $constants
     * @param array $expectedErrors
     * @param array $options
     */
    public function testConstantReferences(array $constants, array $expectedErrors, bool $skipConstants = false)
    {

        $errorCount = count($expectedErrors);

        foreach ($constants as $constant) {
            $this->helper
                ->shouldReceive("replaceConstantReference")
                ->with(\Mockery::any(), $constant, '', true)
                ->times($skipConstants? 0: 1)
                ->andReturnArg(0);
        }

        $constants[] = null;
        $this->helper->shouldReceive("findNextConstant")->andReturnValues($constants);

        $this->helper->shouldReceive("referenceError")->andReturnUsing(function ($message) use (&$expectedErrors) {
            foreach ($expectedErrors as $i => $expectedError) {
                if (preg_match($expectedError, $message)) {
                    unset($expectedErrors[$i]);
                    return $this->error;
                }
            }
            $this->fail("The validation error message '$message' was not expected (could it have been raised twice?)");
        });

        $helper = new ReferenceValidatorHelper($this->helper);

        $errors = $helper->checkConstantReferences("anything", ["skipConstants" => $skipConstants]);

        $this->assertCount($errorCount, $errors);

        $this->assertEmpty($expectedErrors, "There were expected errors that were not raised");
    }

    /**
     * @dataProvider servicesProvider
     *
     * @param array $services
     * @param string $value
     * @param bool|string $expected
     * @param array $options
     */
    public function testServiceReferences(
        array $services,
        string $value,
        bool $isService,
        $expected,
        array $options = []
    ) {
        $this->helper->shouldReceive("isServiceReference")->andReturn($isService);
        $this->helper->shouldReceive("getServiceKey")->andReturnArg(0);

        $helper = new ReferenceValidatorHelper($this->helper);
        $helper->setDefinitions(["services" => $services]);

        $this->assertSame($expected, $helper->checkServiceReference($value, $options));
    }

    /**
     * @dataProvider circularReferenceProvider
     *
     * @param string $service
     * @param bool $result
     * @param array $references
     * @param array $secondaryReferences
     */
    public function testCircularReferences(
        string $service,
        bool $result,
        array $references,
        array $secondaryReferences = []
    ) {
        $helper = new ReferenceValidatorHelper($this->helper);

        $this->assertSame($result, $helper->findCircularReferences($service, $references, $secondaryReferences));
    }

    /**
     * @return array
     */
    public function parametersProvider(): array
    {
        return [
            "No references" => [
                [],
                [],
                [],
                []
            ],
            "Reference doesn't exist" => [
                [],
                ["missingReference"],
                ["/missingReference/"],
                []
            ],
            "Reference does exist" => [
                ["goodReference" => "blah"],
                ["goodReference"],
                [],
                ["goodReference"]
            ],
            "Multiple missing references" => [
                [],
                ["one", "two", "three"],
                ["/one/", "/two/", "/three/"],
                []
            ],
            "Multiple existing references" => [
                ["one" => "blah", "two" => "blah", "three" => "blah"],
                ["one", "two", "three"],
                [],
                ["one", "two", "three"]
            ],
            "Missing and existing references" => [
                ["one" => "blah", "three" => "blah"],
                ["one", "two", "three", "four"],
                ["/two/", "/four/"],
                ["one", "three"]
            ],
            "Options set to skip parameters" => [
                ["one" => "blah"],
                ["one", "two"],
                [],
                [],
                true
            ]
        ];
    }

    /**
     * @return array
     */
    public function constantProvider(): array
    {
        return [
            "No constant references" => [
                [],
                []
            ],
            "Constant exists" => [
                ["PHP_EOL"],
                []
            ],
            "Constant not defined" => [
                ["MISSING"],
                ["/MISSING.*does not exist/"]
            ],
            "Class constant exists" => [
                ["Lexide\Syringe\Test\Unit\Validation\ReferenceValidatorHelperTest::TEST"],
                []
            ],
            "Class exists but constant does not" => [
                ["Lexide\Syringe\Test\Unit\Validation\ReferenceValidatorHelperTest::MISSING"],
                ["/MISSING.*does not exist/"]
            ],
            "Class does not exist" => [
                ["Non\Existent::CONSTANT"],
                ["/class.*Non\\\\Existent.*CONSTANT/"]
            ],
            "Multiple constants" => [
                ["PHP_EOL", "DIRECTORY_SEPARATOR", "DateTimeZone::AFRICA"],
                []
            ],
            "Multiple undefined constants" => [
                ["BAD_ONE", "PHP_EOL", "DateTimeZone::MOON"],
                ["/BAD_ONE/", "/DateTimeZone::MOON/"]
            ],
            "Options set to skip constants" => [
                ["PHP_EOL", "BAD"],
                [],
                true
            ]
        ];
    }

    /**
     * @return array[]
     */
    public function servicesProvider(): array
    {
        return [
            "Not a service reference" => [
                [],
                "not a service",
                false,
                true
            ],
            "Service doesn't exist" => [
                [],
                "missing",
                true,
                false
            ],
            "Service does exist" => [
                ["exists" => ["foo" => "bar"]],
                "exists",
                true,
                "exists"
            ],
            "Options set to skip services (when missing)" => [
                [],
                "missing",
                true,
                true,
                ["skipServices" => true]
            ],
            "Options set to skip services (when exists)" => [
                ["exists" => ["foo" => "bar"]],
                "exists",
                true,
                true,
                ["skipServices" => true]
            ]
        ];
    }

    /**
     * @return array[]
     */
    public function circularReferenceProvider(): array
    {
        return [
            "service has no references" => [
                "test",
                false,
                ["test" => []]
            ],
            "service's references are flat" => [
                "test",
                false,
                [
                    "test" => ["one", "two", "three"],
                    "one" => [],
                    "two" => [],
                    "three" => []
                ]
            ],
            "service's references are nested" => [
                "test",
                false,
                [
                    "test" => ["one", "two", "three"],
                    "one" => ["four"],
                    "two" => [],
                    "three" => ["five", "six"],
                    "four" => [],
                    "five" => ["seven"],
                    "six" => [],
                    "seven" => []
                ]
            ],
            "service's references are nested and reused" => [
                "test",
                false,
                [
                    "test" => ["one", "two", "three"],
                    "one" => ["four"],
                    "two" => ["three"],
                    "three" => ["five", "six"],
                    "four" => ["three"],
                    "five" => ["seven"],
                    "six" => ["seven"],
                    "seven" => []
                ]
            ],
            "service references itself" => [
                "test",
                true,
                ["test" => ["test"]]
            ],
            "service's references use the service" => [
                "test",
                true,
                [
                    "test" => ["one", "two", "three"],
                    "one" => [],
                    "two" => ["test"],
                    "three" => []
                ]
            ],
            "circular dependency loop of 3 services" => [
                "test",
                true,
                [
                    "test" => ["one", "two", "three"],
                    "one" => [],
                    "two" => ["four"],
                    "three" => [],
                    "four" => ["test"]
                ]
            ],
            "Deeply nested circular dependency" => [
                "test",
                true,
                [
                    "test" => ["one"],
                    "one" => ["two"],
                    "two" => ["three"],
                    "three" => ["four"],
                    "four" => ["five"],
                    "five" => ["test"]
                ]
            ],
            "Circular dependency loop starting in a dependency" => [
                "test",
                true,
                [
                    "test" => ["one"],
                    "one" => ["two"],
                    "two" => ["three"],
                    "three" => ["four"],
                    "four" => ["five"],
                    "five" => ["three"]
                ]
            ],
            "Nested dependencies using secondary reference list" => [
                "test",
                false,
                [
                    "test" => ["one", "two", "three"],
                    "one" => ["four"],
                    "three" => []
                ],
                [
                    "two" => ["three"],
                    "four" => ["five"],
                    "five" => []
                ]
            ],
            "Circular dependency using secondary reference list" => [
                "test",
                true,
                [
                    "test" => ["one"],
                    "one" => ["two"],
                    "three" => ["four"]
                ],
                [
                    "two" => ["three"],
                    "four" => ["two"]
                ]
            ]
        ];
    }

}
