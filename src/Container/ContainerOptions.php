<?php

namespace Lexide\Syringe\Container;

use Pimple\Container;
use Psr\Log\LoggerInterface;

class ContainerOptions
{

    protected array $options = [
        "useIncludePath" => true,
        "skipSyntaxValidation" => false,
        "cacheCompiledDefinition" => true,
        "applicationDirectory" => null,   // no default, needs to be set
        "applicationDirectoryKey" => "app.dir",
        "containerClass" => Container::class,
        "usePsrContainer" => false,
        "serviceFactoryClass" => ServiceFactory::class,
        "ignoreCompilationWarnings" => false,
        "ignoreAssertionWarnings" => false,
        "ignoreAllWarnings" => false,
        "environmentVariableMap" => [],
        "noStubs" => false,
        "processAssertions" => true,
        "errorLogger" => null
    ];

    /**
     * @param string $name
     * @param bool|string|int|float|array|LoggerInterface|null $value
     * @return bool|string|int|float|array|LoggerInterface|null
     */
    protected function accessOption(
        string $name,
        bool|string|int|float|array|LoggerInterface|null $value = null
    ): bool|string|int|float|array|LoggerInterface|null {

        if (is_null($value)) {
            // getter
            return $this->options[$name] ?? null;
        }

        // setter
        $this->options[$name] = $value;
        return $value;

    }

    /**
     * @param ?bool $use
     * @return bool
     */
    public function useIncludePath(?bool $use = null): bool
    {
        return $this->accessOption("useIncludePath", $use);
    }

    /**
     * @param ?bool $skip
     * @return bool
     */
    public function skipSyntaxValidation(?bool $skip = null): bool
    {
        return $this->accessOption("skipSyntaxValidation", $skip);
    }

    /**
     * @param ?bool $cacheEnabled
     * @return bool
     */
    public function cacheCompiledDefinition(?bool $cacheEnabled = null): bool
    {
        return $this->accessOption("cacheCompiledDefinition", $cacheEnabled);
    }

    /**
     * @param ?string $directory
     * @return ?string
     */
    public function applicationDirectory(?string $directory = null): ?string
    {
        return $this->accessOption("applicationDirectory", $directory);
    }

    /**
     * @param ?string $key
     * @return ?string
     */
    public function applicationDirectoryKey(?string $key = null): ?string
    {
        return $this->accessOption("applicationDirectoryKey", $key);
    }

    /**
     * @param ?string $containerClass
     * @return string
     */
    public function containerClass(?string $containerClass = null): string
    {
        return $this->accessOption("containerClass", $containerClass);
    }

    /**
     * @param ?string $serviceFactoryClass
     * @return string
     */
    public function serviceFactoryClass(?string $serviceFactoryClass = null): string
    {
        return $this->accessOption("serviceFactoryClass", $serviceFactoryClass);
    }

    /**
     * @param ?bool $ignore
     * @return bool
     */
    public function ignoreCompilationWarnings(?bool $ignore = null): bool
    {
        return $this->accessOption("ignoreCompilationWarnings", $ignore) || $this->ignoreAllWarnings();
    }

    /**
     * @param ?bool $ignore
     * @return bool
     */
    public function ignoreAssertionWarnings(?bool $ignore = null): bool
    {
        return $this->accessOption("ignoreAssertionWarnings", $ignore) || $this->ignoreAllWarnings();
    }

    /**
     * @param ?bool $ignore
     * @return bool
     */
    public function ignoreAllWarnings(?bool $ignore = null): bool
    {
        return (bool) $this->accessOption("ignoreAllWarnings", $ignore);
    }

    /**
     * @param ?array $variableMap
     * @return array
     */
    public function environmentVariableMap(?array $variableMap = null): array
    {
        return $this->accessOption("environmentVariableMap", $variableMap);
    }

    /**
     * @param ?bool $usePsrContainer
     * @return bool
     */
    public function usePsrContainer(?bool $usePsrContainer = null): bool
    {
        return $this->accessOption("usePsrContainer", $usePsrContainer);
    }

    /**
     * @param ?bool $noStubs
     * @return bool
     */
    public function noStubs(?bool $noStubs = null): bool
    {
        return $this->accessOption("noStubs", $noStubs);
    }

    /**
     * @param ?bool $processAssertions
     * @return bool
     */
    public function processAssertions(?bool $processAssertions = null): bool
    {
        return $this->accessOption("processAssertions", $processAssertions);
    }

    /**
     * @param ?LoggerInterface $logger
     * @return ?LoggerInterface
     */
    public function errorLogger(?LoggerInterface $logger = null): ?LoggerInterface
    {
        return $this->accessOption("errorLogger", $logger);
    }

}