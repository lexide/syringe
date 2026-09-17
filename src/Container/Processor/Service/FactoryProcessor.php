<?php

namespace Lexide\Syringe\Container\Processor\Service;

class FactoryProcessor
{

    /**
     * @param array $definition
     * @return array - config for the service factory
     */
    public function process(array $definition): array
    {
        if (empty($definition["factoryMethod"])) {
            return [];
        }

        $factoryConfig = [
            "method" => $definition["factoryMethod"],
        ];

        if (!empty($definition["factoryService"])) {
            $factoryConfig["service"] = $definition["factoryService"];
        } elseif (!empty($definition["factoryClass"])) {
            $factoryConfig["class"] = $definition["factoryClass"];
        }

        return $factoryConfig;

    }

}