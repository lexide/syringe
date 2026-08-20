<?php

namespace Lexide\Syringe\Compiler;

use Lexide\Syringe\Error\ReportErrorsTrait;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Provider\DefinitionProviderInterface;
use Lexide\Syringe\Validation\SyntaxValidator;
use Psr\Log\LoggerInterface;

class DefinitionLoader
{
    use ReportErrorsTrait;

    protected ConfigLoader $configLoader;
    protected SyntaxValidator $syntaxValidator;

    protected array $loadedFiles = [];

    /**
     * @param ConfigLoader $configLoader
     * @param SyntaxValidator $syntaxValidator
     * @param ?LoggerInterface $errorLogger
     */
    public function __construct(
        ConfigLoader $configLoader,
        SyntaxValidator $syntaxValidator,
        ?LoggerInterface $errorLogger = null
    ) {
        $this->configLoader = $configLoader;
        $this->syntaxValidator = $syntaxValidator;
        $this->errorLogger = $errorLogger;
    }

    /**
     * @param array $configFiles
     * @param DefinitionProviderInterface[] $providers
     * @param bool $skipSyntaxValidation
     * @param bool $ignoreWarnings
     * @return array
     * @throws ConfigException
     */
    public function loadDefinitions(array $configFiles, array $providers, bool $skipSyntaxValidation = false, bool $ignoreWarnings = false): array
    {
        $this->loadedFiles = [];

        /** @var SyringeError[] $errors */
        $errors = [];
        $definitions = [];
        foreach ($configFiles as ["file" => $file, "namespace" => $namespace]) {
            [$fileDefinitions, $fileErrors] = $this->loadDefinition($file, $skipSyntaxValidation);
            $errors = array_merge($errors, $fileErrors);
            $definitions[$namespace] = $this->mergeDefinitions($definitions[$namespace] ?? [], $fileDefinitions);
        }
        foreach ($providers as $provider) {
            $providerDefinitions = $provider->getDefinitions();
            unset($providerDefinitions["imports"]); // no imports for provided definitions

            $namespace = $provider->getNamespace();

            $errors = array_merge($errors, $this->validateSyntax($providerDefinitions, $provider->getName(), $skipSyntaxValidation));
            $definitions[$namespace] = $this->mergeDefinitions($definitions[$namespace] ?? [], $providerDefinitions);
        }

        $this->reportErrors($errors, $ignoreWarnings);

        return $definitions;
    }

    /**
     * @param string $file
     * @param bool $skipSyntaxValidation
     * @param string $relativeTo
     * @return array
     * @throws ConfigException
     */
    protected function loadDefinition(string $file, bool $skipSyntaxValidation, string $relativeTo = ""): array
    {
        [$definitions, $filePath] = $this->configLoader->loadConfig($file, $relativeTo);
        if (isset($this->loadedFiles[$filePath])) {
            throw new ConfigException("Cannot load the same config file twice: $filePath");
        }
        $this->loadedFiles[$filePath] = true;

        $errors = $this->validateSyntax($definitions, $filePath, $skipSyntaxValidation);

        if (!empty($definitions["imports"])) {
            $importedDefinitions = [];
            foreach ($definitions["imports"] as $importFile) {
                [$importDefinitions, $importErrors] = $this->loadDefinition($importFile, $skipSyntaxValidation, $filePath);
                $errors = array_merge($errors, $importErrors);

                $importedDefinitions = $this->mergeDefinitions($importedDefinitions, $importDefinitions);
            }
            $definitions = $this->mergeDefinitions($importedDefinitions, $definitions);
            unset($definitions["imports"]);
        }
        return [$definitions, $errors];
    }

    /**
     * @param array $definition
     * @param string $source
     * @param bool $skipSyntaxValidation
     * @return array
     */
    protected function validateSyntax(array $definition, string $source, bool $skipSyntaxValidation): array
    {
        return $skipSyntaxValidation ? [] : $this->syntaxValidator->validate($definition, $source);
    }

    /**
     * @param array $definitions
     * @param array $newDefinitions
     * @return array
     */
    protected function mergeDefinitions(array $definitions, array $newDefinitions): array
    {
        // extensions and assertions need to be handled differently as they get overwritten with array_replace_recursive
        $toMerge = ["extensions" => true, "assertions" => true];
        $mergeValues = array_intersect_key($definitions, $toMerge);

        $definitions = array_replace_recursive($definitions, $newDefinitions);

        // merge keys
        foreach ($mergeValues as $key => $value) {
            if (is_array($value)) {
                $definitions[$key] = array_merge_recursive($value, $newDefinitions[$key] ?? []);
            }
        }

        return $definitions;
    }
}