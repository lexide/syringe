<?php

namespace Lexide\Syringe\Container;
use Lexide\Syringe\Exception\ServiceException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Pimple\Container;


/**
 * ServiceFactory
 */
interface ServiceFactoryInterface
{

    /**
     * @param Container $container
     */
    public function setContainer(Container $container): void;

    /**
     * @param ReferenceResolverInterface $resolver
     */
    public function setReferenceResolver(ReferenceResolverInterface $resolver): void;

    /**
     * @param string $class
     * @param array $factoryConfig
     * @param array $arguments
     * @param array $calls
     * @return object
     * @throws ServiceException
     */
    public function createService(string $class, array $factoryConfig, array $arguments, array $calls): object;

    /**
     * @param string $key
     * @param array $definition
     * @return object
     * @throws ServiceException
     */
    public function createStub(string $key, array $definition): object;
}
