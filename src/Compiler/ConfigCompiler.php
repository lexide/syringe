<?php

namespace Lexide\Syringe\Compiler;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\LoaderException;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Normalisation\DefinitionsNormaliser;
use Lexide\Syringe\Validation\ReferenceValidator;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\ValidationError;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ConfigCompiler
{

    /**
     * @var ConfigLoader
     */
    protected $loader;

    /**
     * @var SyntaxValidator
     */
    protected $syntaxValidator;

    /**
     * @var DefinitionsNormaliser
     */
    protected $definitionsNormaliser;

    /**
     * @var ReferenceValidator
     */
    protected $referenceValidator;

    /**
     * @var LoggerInterface|null
     */
    protected $errorLogger;

    public function __construct(
        ConfigLoader $loader,
        SyntaxValidator $syntaxValidator,
        DefinitionsNormaliser $definitionsNormaliser,
        ReferenceValidator $referenceValidator,
        LoggerInterface $errorLogger = null
    ) {
        $this->loader = $loader;
        $this->syntaxValidator = $syntaxValidator;
        $this->definitionsNormaliser = $definitionsNormaliser;
        $this->referenceValidator = $referenceValidator;
        $this->errorLogger = $errorLogger;
    }

    /**
     * @param array $configFiles
     * @param array $options
     * @return array
     * @throws ConfigException|LoaderException|ReferenceException
     */
    public function compile(array $configFiles, array $options = []): array
    {
        /** @var ValidationError[] $errors */
        $errors = [];
        $definitions = [];
        foreach ($configFiles as ["file" => $file, "namespace" => $namespace]) {
            [$fileDefinitions, $fileErrors] = $this->loadDefinitions($file);
            $errors = array_merge($errors, $fileErrors);
            $definitions[$namespace] = array_replace_recursive($definitions[$namespace] ?? [], $fileDefinitions);
        }
        $this->reportErrors($errors, $options);

        $namespaces = array_keys($definitions);

        [$normalisedDefinitions, $errors] = $this->definitionsNormaliser->normalise($definitions);
        $this->reportErrors($errors, $options);

        $this->reportErrors(
            $this->referenceValidator->validate($normalisedDefinitions),
            $options
        );

        return ["definitions" => $normalisedDefinitions, "namespaces" => $namespaces];
    }

    /**
     * @param string $file
     * @param string $relativeTo
     * @return array
     * @throws ConfigException
     * @throws LoaderException
     */
    protected function loadDefinitions(string $file, string $relativeTo = ''): array
    {
        [$definitions, $filePath] = $this->loader->loadConfig($file, $relativeTo);
        $errors = $this->syntaxValidator->validateFile($definitions, $filePath);

        if (!empty($definitions["imports"])) {
            foreach ($definitions["imports"] as $importFile) {
                [$importDefinitions, $importErrors] = $this->loadDefinitions($importFile, $filePath);
                $errors = array_merge($errors, $importErrors);
                $definitions = array_replace_recursive($importDefinitions, $definitions);
            }
            unset($definitions["imports"]);
        }
        return [$definitions, $errors];
    }

    /**
     * @param ValidationError[] $errors
     * @param array $options
     * @throws ConfigException
     */
    protected function reportErrors(array $errors, array $options): void
    {
        if (empty($errors)) {
            return;
        }

        if (!empty($options["ignoreWarnings"])) {
            $errors = array_filter($errors, function ($error) {
                return $error->getType() != "warning";
            });
        }
        if (!empty($errors)) {
            if ($this->errorLogger instanceof LoggerInterface) {
                foreach ($errors as $error) {
                    $this->errorLogger->log(
                        $error->getType() == "warning"? LogLevel::WARNING: LogLevel::ERROR,
                        $error->getMessage(),
                        $error->getContext()
                    );
                }
            }
            $errorCount = count($errors);
            $message = $errorCount == 1
                ? "Error: {$errors[0]->getMessage()} " . json_encode($errors[0]->getContext())
                : "There were $errorCount validation errors. See the log for more details";
            throw new ConfigException($message);
        }
    }

}