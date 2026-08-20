<?php

namespace Lexide\Syringe\Container\Processor;

use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Pimple\Container;

class ParametersProcessor
{

    protected ReferenceResolverInterface $resolver;

    /**
     * @param ReferenceResolverInterface $resolver
     */
    public function __construct(ReferenceResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array $definitions
     * @param Container $container
     */
    public function process(array $definitions, Container $container): void
    {
        if (!isset($definitions["parameters"])) {
            return;
        }

        foreach ($definitions["parameters"] as $key => $value) {
            $container[$key] = function () use ($value, $container, $key) {
                try {
                    return $this->resolver->resolveParameter($value, $container);
                } catch (ReferenceException $e) {
                    throw new ReferenceException("Error with key '$key'. " . $e->getMessage(), previous: $e);
                }
            };
        }
    }

}