<?php

namespace Lexide\Syringe\Provider;


use Lexide\Syringe\ServiceLocator;

class SyringeProvider implements DefinitionProviderInterface
{

    /**
     * {@inheritDoc}
     */
    public function getDefinitions(): array
    {
        return [
            "services" => [
                "serviceLocator" => [
                    "class" => ServiceLocator::class
                ]
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace(): string
    {
        return "lexide_syringe";
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return "SyringeProvider";
    }


}