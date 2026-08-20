<?php

namespace Lexide\Syringe\Container\Processor\Service;

use Lexide\Syringe\Container\ServiceFactoryInterface;
use Lexide\Syringe\Exception\ConfigException;
use Pimple\Container;

class StubProcessor
{

    protected ServiceFactoryInterface $serviceFactory;

    /**
     * @param ServiceFactoryInterface $serviceFactory
     */
    public function __construct(ServiceFactoryInterface $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
    }

    /**
     * @param string $key
     * @param array $definition
     * @param Container $container
     * @param bool $noStubs
     * @return bool - stop processing if true
     * @throws ConfigException
     */
    public function process(string $key, array $definition, Container $container, bool $noStubs): bool
    {
        if (!empty($definition["stub"])) {
            if ($noStubs) {
                throw new ConfigException("The service '$key' is a stub, which is not allowed by the current configuration");
            }
            $container[$key] = function() use ($key, $definition) {
                return $this->serviceFactory->createStub($key, $definition);
            };
            return true;
        }
        return false;
    }

}