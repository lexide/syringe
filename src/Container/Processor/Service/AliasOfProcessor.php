<?php

namespace Lexide\Syringe\Container\Processor\Service;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Pimple\Container;

class AliasOfProcessor
{

    protected ReferenceResolverInterface $resolver;

    protected array $serviceAliases = [];

    /**
     * @param ReferenceResolverInterface $resolver
     */
    public function __construct(ReferenceResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param string $key
     * @param array $definition
     * @param Container $container
     * @return bool - stop processing if true
     * @throws ConfigException
     */
    public function process(string $key, array $definition, Container $container): bool
    {
        if (!empty($definition["aliasOf"])) {
            // override any existing definitions for this key
            $aliasedService = $definition["aliasOf"];
            $container[$key] = function() use ($container, $aliasedService) {
                return $this->resolver->resolveService($aliasedService, $container);
            };
            $this->serviceAliases[$key] = true;
            return true;
        }


        // check for collisions
        if (isset($container[$key])) {
            if (isset($this->serviceAliases[$key])) {
                // this service has been aliased by another service. We can ignore the definition.
                return true;
            }
            throw new ConfigException("Tried to define a service named '$key', but that name already exists in the container");
        }
        return false;
    }

}