<?php

namespace Lexide\Syringe\Container;

use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Exception\ServiceException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Pimple\Container;

/**
 * ServiceFactory
 */
class ServiceFactory implements ServiceFactoryInterface
{

    protected Container $container;
    protected ReferenceResolverInterface $resolver;

    /**
     * {@inheritDoc}
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function setReferenceResolver(ReferenceResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * @throws ServiceException
     */
    protected function checkDependencies(): void
    {
        if (empty($this->container)) {
            throw new ServiceException("A container has not been set on this service factory");
        }

        if (empty($this->resolver)) {
            throw new ServiceException("A reference resolver has not been set on this service factory");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createService(string $class, array $factoryConfig, array $arguments, array $calls): object
    {
        $this->checkDependencies();

        try {

            // resolve any parameters or services in the constructor and call arguments (thus finding resolution exceptions ASAP)
            $arguments = $this->resolveArguments($arguments, $class, "__construct");
            foreach ($calls as $i => $call) {
                $call["arguments"] = $this->resolveArguments($call["arguments"], $class, $call["method"]);
                $calls[$i] = $call;
            }
            unset($call);

            // create the service instance
            if (empty($factoryConfig["class"]) && empty($factoryConfig["service"])) {
                $service = new $class(...$arguments);
            } else {
                // create via factory
                $factory = empty($factoryConfig["class"])
                    ? $this->resolver->resolveService($factoryConfig["service"], $this->container)
                    : $factoryConfig["class"];
                $service = call_user_func_array([$factory, $factoryConfig["method"]], $arguments);
            }

            // setter injection
            foreach ($calls as $call) {
                call_user_func_array([$service, $call["method"]], $call["arguments"]);
            }
        } catch (ReferenceException $e) {
            throw new ServiceException("Resolution error when creating service", previous: $e);
        }

        return $service;
    }

    /**
     * {@inheritDoc}
     */
    public function createStub(string $key, array $definition): object
    {
        throw new ServiceException("Service '$key' is a stub service and cannot be accessed or injected.");
    }

    /**
     * @param array $arguments
     * @param string $class
     * @param string $method
     * @return array
     * @throws ReferenceException
     */
    protected function resolveArguments(array $arguments, string $class, string $method): array
    {
        $finalArgs = [];
        foreach ($arguments as $key => $value) {
            // resolve the key for parameters
            $key = $this->resolver->resolveParameter($key, $this->container);

            if (is_array($value)) {
                $value = $this->resolveArguments($value, $class, $method);
            } else {
                // resolve the value for services, parameters or tags
                $value = $this->resolver->resolveArgument($value, $this->container, $class, $method, $key);
            }

            $finalArgs[$key] = $value;
        }
        return $finalArgs;
    }

} 
