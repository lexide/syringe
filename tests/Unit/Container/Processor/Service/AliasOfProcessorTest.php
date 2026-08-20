<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor\Service;

use Lexide\Syringe\Container\Processor\Service\AliasOfProcessor;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class AliasOfProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected ReferenceResolverInterface|MockInterface $resolver;

    protected Container|MockInterface $container;

    public function setUp(): void
    {
        $this->resolver = \Mockery::mock(ReferenceResolverInterface::class);
        $this->container = \Mockery::mock(Container::class);
    }

    public function testAliasing()
    {
        $service = "foo";
        $alias = "bar";
        $definition = [
            "aliasOf" => $alias
        ];

        $this->container->shouldReceive("offsetSet")
            ->with($service, \Mockery::any())
            ->once()
            ->andReturnUsing(function($key, $callable) {
                $callable();
                return null;
            });

        $this->resolver->shouldReceive("resolveService")->with($alias, \Mockery::any())->once();

        $processor = new AliasOfProcessor($this->resolver);
        $processor->process($service, $definition, $this->container);
    }

    public function testIgnoringAliasOfCollisions()
    {
        $service = "foo";
        $serviceDefinition = [
            "blah" => "blah"
        ];

        $alias = "bar";
        $aliasDefinition = [
            "aliasOf" => $alias
        ];

        $this->container->shouldReceive("offsetSet")->with($service, \Mockery::any())->once();
        $this->container->shouldReceive("offsetExists")->with($service)->andReturnTrue();

        $processor = new AliasOfProcessor($this->resolver);
        $processor->process($service, $aliasDefinition, $this->container);

        $processor->process($service, $serviceDefinition, $this->container);
    }

    public function testKeyCollisions()
    {

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/already exists/");

        $service = "foo";
        $serviceDefinition = [
            "blah" => "blah"
        ];

        $this->container->shouldReceive("offsetExists")->with($service)->andReturnTrue();

        $processor = new AliasOfProcessor($this->resolver);
        $processor->process($service, $serviceDefinition, $this->container);
    }
}
