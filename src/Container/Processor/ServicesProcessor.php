<?php

namespace Lexide\Syringe\Container\Processor;

use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Container\Processor\Service\AliasOfProcessor;
use Lexide\Syringe\Container\Processor\Service\ClassProcessor;
use Lexide\Syringe\Container\Processor\Service\FactoryProcessor;
use Lexide\Syringe\Container\Processor\Service\PrivateProcessor;
use Lexide\Syringe\Container\Processor\Service\StubProcessor;
use Lexide\Syringe\Container\Processor\Service\TagProcessor;
use Lexide\Syringe\Container\ServiceFactoryInterface;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Pimple\Container;

class ServicesProcessor
{

    protected ServiceFactoryInterface $serviceFactory;
    protected StubProcessor $stubProcessor;
    protected AliasOfProcessor $aliasOfProcessor;
    protected ClassProcessor $classProcessor;
    protected PrivateProcessor $privateProcessor;
    protected FactoryProcessor $factoryProcessor;
    protected TagProcessor $tagProcessor;

    /**
     * @param ServiceFactoryInterface $serviceFactory
     * @param StubProcessor $stubProcessor
     * @param AliasOfProcessor $aliasOfProcessor
     * @param ClassProcessor $classProcessor
     * @param PrivateProcessor $privateProcessor
     * @param FactoryProcessor $factoryProcessor
     * @param TagProcessor $tagProcessor
     */
    public function __construct(
        ServiceFactoryInterface $serviceFactory,
        StubProcessor $stubProcessor,
        AliasOfProcessor $aliasOfProcessor,
        ClassProcessor $classProcessor,
        PrivateProcessor $privateProcessor,
        FactoryProcessor $factoryProcessor,
        TagProcessor $tagProcessor
    ) {
        $this->serviceFactory = $serviceFactory;
        $this->stubProcessor = $stubProcessor;
        $this->aliasOfProcessor = $aliasOfProcessor;
        $this->classProcessor = $classProcessor;
        $this->privateProcessor = $privateProcessor;
        $this->factoryProcessor = $factoryProcessor;
        $this->tagProcessor = $tagProcessor;
    }

    /**
     * @param array $definitions
     * @param Container $container
     * @param ContainerOptions $options
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function process(array $definitions, Container $container, ContainerOptions $options): void
    {
        if (!isset($definitions["services"])) {
            return;
        }

        $noStubs = $options->noStubs();

        // process services
        foreach ($definitions["services"] as $key => $definition) {

            if ($this->stubProcessor->process($key, $definition, $container, $noStubs)) {
                continue;
            }
            if ($this->aliasOfProcessor->process($key, $definition, $container)) {
                continue;
            }

            $class = $this->classProcessor->process($definition);
            $key = $this->privateProcessor->process($key, $definition);
            $factoryConfig = $this->factoryProcessor->process($definition);
            $this->tagProcessor->process($key, $definition, $container);

            $arguments = $definition["arguments"] ?? [];
            $calls = $definition["calls"] ?? [];

            $container[$key] = function() use ($class, $factoryConfig, $arguments, $calls) {
                return $this->serviceFactory->createService($class, $factoryConfig, $arguments, $calls);
            };

        }

        $this->serviceFactory->setContainer($container);
    }

}