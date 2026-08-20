<?php

namespace Lexide\Syringe\Test\Integration;

use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Syringe;
use Lexide\Syringe\Tag\TagIterator;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class EverythingTest extends TestCase
{

    protected array $expectedParameters = [
        "override" => "THIS",
        "importOverride" => "THIS",
        "import.one" => true,
        "import.two" => true,
        "import.three" => true,
        "alpha.import.four" => true,
        "url" => "https://test.example.com/a/short/path?foo=bar&baz=fiz",
        "config" => [
            [
                "one" => "one",
                "two" => "two",
                "three" => "three",
                "four" => [
                    "five",
                    "six"
                ]
            ]
        ],
        "parameter.escaping" => "The parameter character % can be escaped",
        "constant.value" => 9223372036854775807,
        "constant.substitution" => "The service reference character is @",
        "constant.escaping" => "The constant character ^ can also be escaped",
        "env.one" => "abc",
        "env.two" => "def",
        "env.three" => "ghi"
    ];

    public function testEverything()
    {
        $logger = new EchoLogger();

        $envVarMap = [
            "SYRINGE_ONE" => "env.one",
            "SYRINGE_TWO" => "env.two",
            "SYRINGE_THREE" => "env.three"
        ];
        $envVars = [
            "SYRINGE_ONE" => "abc",
            "SYRINGE_TWO" => "def",
            "SYRINGE_THREE" => "ghi"
        ];

        $options = new ContainerOptions();
        $options->applicationDirectory(dirname(dirname(__DIR__)));
        $options->errorLogger($logger);
        $options->cacheCompiledDefinition(false);
        $options->environmentVariableMap($envVarMap);
        $options->noStubs(true);

        foreach ($envVars as $var => $value) {
            putenv("$var=$value");
        }

        $syringe = new Syringe($options);
        $syringe->addConfigPath("tests/Integration/config");
        $syringe->addConfigFiles([
            "alpha" => "alpha.yml",
            "" => "services.yml"
        ]);

        $container = $syringe->build();
        // test parameters
        foreach ($this->expectedParameters as $parameter => $value) {
            $this->assertSame($value, $container[$parameter]);
        }

        $serviceOne = $container["service.one"];
        $this->assertInstanceOf(MockService::class, $serviceOne);
        $this->assertSame(1, $serviceOne->getThing());

        $serviceTwo = $container["service.two"];
        $this->assertInstanceOf(MockService::class, $serviceTwo);
        $this->assertSame(2, $serviceTwo->getThing());

        $serviceThree = $container["service.three"];
        $this->assertInstanceOf(MockService::class, $serviceThree);
        $this->assertSame(3, $serviceThree->getThing());

        $serviceFour = $container["service.four"];
        $this->assertInstanceOf(MockService::class, $serviceFour);
        $this->assertSame(4, $serviceFour->getThing());

        $serviceFive = $container["service.five"];
        $this->assertInstanceOf(MockService::class, $serviceFive);
        $this->assertSame(5, $serviceFive->getThing());

        $serviceSix = $container["service.six"];
        $this->assertInstanceOf(MockService::class, $serviceSix);
        $this->assertSame(6, $serviceSix->getThing());

        $this->assertSame($serviceTwo, $serviceOne->getServices()["private"]);
        $this->assertSame($serviceTwo, $serviceThree->getServices()["alias"]);
        $serviceAlpha = $container["alpha.service.extendable"];
        $this->assertInstanceOf(MockService::class, $serviceAlpha);
        $this->assertSame($serviceTwo, $serviceAlpha->getServices()["alphaStub"]);
        $this->assertCount(5, $serviceFour->getServices());

        $this->checkTag(
            $container,
            "mockServices",
            array_values([
                1 => $serviceTwo,
                2 => $serviceSix,
                3 => $serviceOne,
                4 => $serviceThree,
                5 => $serviceFive
            ])
        );

        $this->checkTag(
            $container,
            "keyedServices",
            [
                "four" => $serviceFour,
                "five" => $serviceFive,
                "six" => $serviceSix
            ]
        );

        $this->checkTag(
            $container,
            "extendedTag",
            [
                "extended" => $serviceAlpha
            ]
        );

    }

    protected function checkTag(Container $container, string $tag, array $expectedOrder): void
    {
        /** @var TagIterator $mockServices */
        $tagIterator = new TagIterator($container, $container["#$tag"]);

        foreach ($expectedOrder as $key => $expectedService) {
            if (is_int($key)) {
                $this->assertSame($expectedService, $tagIterator->current());
                $this->assertSame($key, $tagIterator->key());
                $tagIterator->next();
            }
            $this->assertSame($expectedService, $tagIterator[$key], "Tag $tag, key $key, is not the expected service");
        }
    }

}
