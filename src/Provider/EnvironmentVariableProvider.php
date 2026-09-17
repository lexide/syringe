<?php

namespace Lexide\Syringe\Provider;

class EnvironmentVariableProvider implements DefinitionProviderInterface
{

    protected array $environmentVariableMap;

    /**
     * @param array $environmentVariableMap
     */
    public function __construct(array $environmentVariableMap)
    {
        $this->environmentVariableMap = $environmentVariableMap;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinitions(): array
    {
        $environmentVariables = [];
        foreach ($this->environmentVariableMap as $variable => $parameterName) {
            $environmentVariables[$parameterName] = getenv($variable);
        }

        return [
            "parameters" => $environmentVariables
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace(): string
    {
        return "";
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return "EnvironmentMapProvider";
    }

}