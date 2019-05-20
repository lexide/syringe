<?php

namespace Lexide\Syringe;

use Lexide\Syringe\Exception\ServiceException;
use Pimple\Container;

/**
 * ServiceFactory
 */
class ServiceFactory implements ServiceFactoryInterface
{

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ReferenceResolverInterface
     */
    protected $resolver;

    /**
     * @param Container $container
     * @param ReferenceResolverInterface $resolver
     */
    public function __construct(Container $container, ReferenceResolverInterface $resolver)
    {
        $this->container = $container;
        $this->resolver = $resolver;
    }

    /**
     * {@inheritDoc}
     */
    public function createService($class, array $factory, array $arguments, array $calls, $alias = "")
    {
        // resolve any parameters or services in the constructor and call arguments (thus finding resolution exceptions ASAP)
        $arguments = $this->resolveArguments($arguments, $alias);
        foreach ($calls as &$call) {
            $call["arguments"] = $this->resolveArguments($call["arguments"], $alias);
        }
        unset($call);

        // create the service instance
        if (empty($factory["class"]) && empty($factory["service"])) {
            $ref = new \ReflectionClass($class);
            $service = $ref->newInstanceArgs($arguments);
        } else {
            // create via factory
            $factoryClass = empty($factory["class"])
                ? $this->resolver->resolveService($factory["service"], $this->container, $alias)
                : $factory["class"];
            $service = call_user_func_array([$factoryClass, $factory["method"]], $arguments);
        }

        // setter injection
        foreach ($calls as $call) {
            call_user_func_array([$service, $call["method"]], $call["arguments"]);
        }

        return $service;
    }

    /**
     * {@inheritDoc}
     */
    public function aliasService($service, $alias)
    {
        return $this->resolver->resolveService($service, $this->container, $alias);
    }

    /**
     * {@inheritDoc}
     */
    public function extendService($service, array $calls, $alias = "")
    {
        foreach ($calls as $call) {
            $call["arguments"] = $this->resolveArguments($call["arguments"], $alias);
            call_user_func_array([$service, $call["method"]], $call["arguments"]);
        }
        return $service;
    }

    /**
     * {@inheritDoc}
     */
    public function createStub($key, array $definition)
    {
        throw new ServiceException("Service '$key' is a stub service and cannot be accessed or injected.");
    }

    protected function resolveArguments(array $arguments, $alias)
    {
        $finalArgs = [];
        foreach ($arguments as $key => $value) {
            // resolve the key for parameters
            $key = $this->resolver->resolveParameter($key, $this->container, $alias);

            // resolve the value for services, parameters or tags)
            $value = $this->resolver->resolveService($value, $this->container, $alias);
            $value = $this->resolver->resolveParameter($value, $this->container, $alias);
            $value = $this->resolver->resolveTag($value, $this->container);
            $finalArgs[$key] = $value;
        }
        return $finalArgs;
    }

} 
