<?php

namespace Lexide\Syringe\Provider;

interface DefinitionProviderInterface
{

    /**
     * @return array
     */
    public function getDefinitions(): array;

    /**
     * @return string
     */
    public function getNamespace(): string;

    /**
     * @return string
     */
    public function getName(): string;

}