<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor\Service;

use Lexide\Syringe\Container\Processor\Service\StubProcessor;
use Lexide\Syringe\Container\ServiceFactoryInterface;
use Lexide\Syringe\Exception\ConfigException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class StubProcessorTest extends TestCase
{

    use MockeryPHPUnitIntegration;

    protected ServiceFactoryInterface|MockInterface $factory;

    protected Container|MockInterface $container;

    public function setUp(): void
    {
        $this->factory = \Mockery::mock(ServiceFactoryInterface::class);
        $this->container = \Mockery::mock(Container::class);
    }

    public function testCreatingStubs()
    {
        $service = "foo";
        $definition = [
            "stub" => true
        ];

        $this->container->shouldReceive("offsetSet")
            ->with($service, \Mockery::any())
            ->once()
            ->andReturnUsing(function($key, $callable) {
                $callable();
            });

        $this->factory->shouldReceive("createStub")->with($service, \Mockery::any())->once();

        $processor = new StubProcessor($this->factory);
        $processor->process($service, $definition, $this->container, false);
    }

    public function testStubsNotAllowed()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/stub.*not allowed/");

        $service = "foo";
        $definition = [
            "stub" => true
        ];

        $this->container->shouldNotReceive("offsetSet");

        $this->factory->shouldNotReceive("createStub");

        $processor = new StubProcessor($this->factory);
        $processor->process($service, $definition, $this->container, true);
    }

}
