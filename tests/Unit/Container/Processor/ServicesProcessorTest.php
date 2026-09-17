<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor;

use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Container\Processor\Service\AliasOfProcessor;
use Lexide\Syringe\Container\Processor\Service\ClassProcessor;
use Lexide\Syringe\Container\Processor\Service\FactoryProcessor;
use Lexide\Syringe\Container\Processor\Service\PrivateProcessor;
use Lexide\Syringe\Container\Processor\Service\StubProcessor;
use Lexide\Syringe\Container\Processor\Service\TagProcessor;
use Lexide\Syringe\Container\Processor\ServicesProcessor;
use Lexide\Syringe\Container\ServiceFactoryInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class ServicesProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container|MockInterface $container;
    protected ContainerOptions|MockInterface $options;
    protected ServiceFactoryInterface|MockInterface $factory;
    protected StubProcessor|MockInterface $stubProcessor;
    protected AliasOfProcessor|MockInterface $aliasOfProcessor;
    protected ClassProcessor|MockInterface $classProcessor;
    protected PrivateProcessor|MockInterface $privateProcessor;
    protected FactoryProcessor|MockInterface $factoryProcessor;
    protected TagProcessor|MockInterface $tagProcessor;
    protected ServicesProcessor $processor;

    public function setUp(): void
    {
        $this->container = \Mockery::mock(Container::class);
        $this->options = \Mockery::mock(ContainerOptions::class);
        $this->options->shouldIgnoreMissing(false);
        $this->factory = \Mockery::mock(ServiceFactoryInterface::class);
        $this->factory->shouldReceive("setContainer")->with($this->container);
        $this->stubProcessor = \Mockery::mock(StubProcessor::class);
        $this->aliasOfProcessor = \Mockery::mock(AliasOfProcessor::class);
        $this->classProcessor = \Mockery::mock(ClassProcessor::class);
        $this->privateProcessor = \Mockery::mock(PrivateProcessor::class);
        $this->factoryProcessor = \Mockery::mock(FactoryProcessor::class);
        $this->tagProcessor = \Mockery::mock(TagProcessor::class);

        $this->processor = new ServicesProcessor(
            $this->factory,
            $this->stubProcessor,
            $this->aliasOfProcessor,
            $this->classProcessor,
            $this->privateProcessor,
            $this->factoryProcessor,
            $this->tagProcessor
        );
    }

    public function testStubs()
    {
        $service = "foo";
        $noStubs = true;
        $this->options->shouldReceive("noStubs")->andReturn($noStubs);

        $serviceDefinition = [
            "bar" => "baz"
        ];

        $definitions = [
            "services" => [
                $service => $serviceDefinition
            ]
        ];

        $this->stubProcessor->shouldReceive("process")
            ->with($service, $serviceDefinition, $this->container, $noStubs)
            ->once()
            ->andReturnTrue();

        $this->aliasOfProcessor->shouldNotReceive("process");

        $this->processor->process($definitions, $this->container, $this->options);

    }

    public function testAliases()
    {
        $service = "foo";

        $serviceDefinition = [
            "bar" => "baz"
        ];
        $definitions = [
            "services" => [
                $service => $serviceDefinition
            ]
        ];

        $this->stubProcessor->shouldReceive("process")->andReturnFalse();

        $this->aliasOfProcessor->shouldReceive("process")
            ->with($service, $serviceDefinition, $this->container)
            ->once()
            ->andReturnTrue();

        $this->classProcessor->shouldNotReceive("process");

        $this->processor->process($definitions, $this->container, $this->options);
    }

    public function testCreatingService()
    {
        $service = "foo";
        $private = "bar";

        $class = "foo\\bar\\baz";
        $arguments = ["one", "two"];
        $calls = ["three" => "four"];
        $factory = [
            "factory" => true
        ];

        $serviceDefinition = [
            "arguments" => $arguments,
            "calls" => $calls
        ];
        $definitions = [
            "services" => [
                $service => $serviceDefinition
            ]
        ];

        $this->stubProcessor->shouldReceive("process")->andReturnFalse();

        $this->aliasOfProcessor->shouldReceive("process")->andReturnFalse();

        $this->classProcessor->shouldReceive("process")->with($serviceDefinition)->once()->andReturn($class);
        $this->privateProcessor->shouldReceive("process")->with($service, $serviceDefinition)->once()->andReturn($private);
        $this->factoryProcessor->shouldReceive("process")->with($serviceDefinition)->once()->andReturn($factory);
        $this->tagProcessor->shouldReceive("process")->with($private, $serviceDefinition, $this->container)->once();

        $this->container->shouldReceive("offsetSet")
            ->with($private, \Mockery::any())
            ->once()
            ->andReturnUsing(function($key, $callable) {
                $callable();
            });
        $this->factory->shouldReceive("createService")->with($class, $factory, $arguments, $calls)->once();

        $this->processor->process($definitions, $this->container, $this->options);
    }

}
