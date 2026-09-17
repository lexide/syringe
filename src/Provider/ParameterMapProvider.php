<?php

namespace Lexide\Syringe\Provider;

class ParameterMapProvider implements DefinitionProviderInterface
{

    protected array $parameterMap;
    protected ?string $name;
    protected ?string $namespace;

    /**
     * @param array $parameterMap
     * @param ?string $name
     * @param ?string $namespace
     */
    public function __construct(array $parameterMap, ?string $name = null, ?string $namespace = null)
    {
        $this->parameterMap = $parameterMap;
        $this->name = $name;
        $this->namespace = $namespace;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinitions(): array
    {
        return [
            "parameters" => $this->parameterMap
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace(): string
    {
        return $this->namespace ?? "";
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name ?? "ParameterMapProvider";
    }


}