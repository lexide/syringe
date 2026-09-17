<?php

namespace Lexide\Syringe\Container;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;

class ServiceFactoryFactory
{

    /**
     * @param ReferenceResolverInterface $resolver
     * @param string $serviceFactoryClass
     * @return ServiceFactoryInterface
     * @throws ConfigException
     */
    public function create(
        ReferenceResolverInterface $resolver,
        string $serviceFactoryClass = ServiceFactory::class
    ): ServiceFactoryInterface {

        if (!is_subclass_of($serviceFactoryClass, ServiceFactoryInterface::class)) {
            throw new ConfigException(
                "The service factory class '{$serviceFactoryClass}' does not implement the ServiceFactoryInterface"
            );
        }

        $factory = new $serviceFactoryClass();
        $factory->setReferenceResolver($resolver);
        return $factory;

    }

}