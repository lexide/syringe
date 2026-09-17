<?php

namespace Lexide\Syringe;

use Lexide\Syringe\Tag\TagCollection;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Pimple\Container;

/**
 * ServiceLocator
 */
class ServiceLocator
{

    protected ?Container $container = null;

    /**
     * @param ?Container $container
     */
    public function __construct(?Container $container = null)
    {
        if (!empty($container)) {
            $this->setContainer($container);
        }
    }

    /**
     * @param Container $container
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * @param string $serviceName
     * @param bool $resolveTags
     * @return mixed
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function get(string $serviceName, bool $resolveTags = true): mixed
    {
        if (!$this->container instanceof Container) {
            throw new ConfigException("No Container has been set on the ServiceLocator");
        }

        if (!$this->container->offsetExists($serviceName)) {
            throw new ReferenceException("The key '$serviceName' is not registered in this Container");
        }

        $service = $this->container[$serviceName];

        // resolve tags if required
        if ($service instanceof TagCollection && $resolveTags) {
            $services = $service->getServices();
            $service = [];
            foreach ($services as $key => $taggedService) {
                $service[$key] = $this->get($taggedService, false);
            }
        }

        return $service;
    }

}
