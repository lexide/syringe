<?php

namespace Lexide\Syringe;
use Lexide\Syringe\Exception\ServiceException;


/**
 * ServiceFactory
 */
interface ServiceFactoryInterface
{
    /**
     * @param string $class
     * @param array $factory
     * @param array $arguments
     * @param array $calls
     * @param string $alias
     * @return object
     */
    public function createService($class, array $factory, array $arguments, array $calls, $alias = "");

    /**
     * @param string $service
     * @param string $alias
     * @return object
     */
    public function aliasService($service, $alias);

    /**
     * @param string $service
     * @param array $calls
     * @param string $alias
     * @return object
     */
    public function extendService($service, array $calls, $alias = "");

    /**
     * @param string $key
     * @param array $definition
     * @return object
     * @throws ServiceException
     */
    public function createStub($key, array $definition);
}
