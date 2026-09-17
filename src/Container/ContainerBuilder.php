<?php

namespace Lexide\Syringe\Container;

use Lexide\Syringe\Container\Processor\AssertionsProcessor;
use Lexide\Syringe\Container\Processor\ParametersProcessor;
use Lexide\Syringe\Container\Processor\ServicesProcessor;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use Psr\Container\ContainerInterface;

class ContainerBuilder
{

    protected ContainerFactory $containerFactory;
    protected ParametersProcessor $parametersProcessor;
    protected ServicesProcessor $servicesProcessor;
    protected AssertionsProcessor $assertionsProcessor;

    /**
     * @param ContainerFactory $containerFactory
     * @param ParametersProcessor $parametersProcessor
     * @param ServicesProcessor $servicesProcessor
     * @param AssertionsProcessor $assertionsProcessor
     */
    public function __construct(
        ContainerFactory $containerFactory,
        ParametersProcessor $parametersProcessor,
        ServicesProcessor $servicesProcessor,
        AssertionsProcessor $assertionsProcessor
    ) {
        $this->containerFactory = $containerFactory;
        $this->parametersProcessor = $parametersProcessor;
        $this->servicesProcessor = $servicesProcessor;
        $this->assertionsProcessor = $assertionsProcessor;
    }

    /**
     * @param array $serviceDefinitions
     * @param ContainerOptions $options
     * @return Container|ContainerInterface
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function createContainer(array $serviceDefinitions, ContainerOptions $options): Container|ContainerInterface
    {
        $container = $this->containerFactory->create();
        $this->populateContainer($container, $serviceDefinitions, $options);

        if ($options->usePsrContainer()) {
            $container = new PsrContainer($container);
        }

        return $container;
    }

    /**
     * @param Container $container
     * @param array $serviceDefinitions
     * @param ContainerOptions $options
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function populateContainer(Container $container, array $serviceDefinitions, ContainerOptions $options): void
    {
        $this->parametersProcessor->process($serviceDefinitions, $container);
        $this->servicesProcessor->process($serviceDefinitions, $container, $options);
        $this->assertionsProcessor->process($serviceDefinitions, $container, $options);
    }

}
