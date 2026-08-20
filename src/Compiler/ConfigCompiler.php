<?php

namespace Lexide\Syringe\Compiler;

use Lexide\Syringe\Error\ReportErrorsTrait;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Normalisation\DefinitionsNormaliser;
use Lexide\Syringe\Validation\ReferenceValidator;
use Psr\Log\LoggerInterface;

class ConfigCompiler
{
    use ReportErrorsTrait;

    protected DefinitionsNormaliser $definitionsNormaliser;
    protected ReferenceValidator $referenceValidator;

    /**
     * @param DefinitionsNormaliser $definitionsNormaliser
     * @param ReferenceValidator $referenceValidator
     * @param ?LoggerInterface $errorLogger
     */
    public function __construct(
        DefinitionsNormaliser $definitionsNormaliser,
        ReferenceValidator $referenceValidator,
        ?LoggerInterface $errorLogger = null
    ) {
        $this->definitionsNormaliser = $definitionsNormaliser;
        $this->referenceValidator = $referenceValidator;
        $this->errorLogger = $errorLogger;
    }

    /**
     * @param array $definitions
     * @param bool $ignoreWarnings
     * @return array
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function compile(array $definitions, bool $ignoreWarnings = false): array
    {
        $namespaces = array_keys($definitions);

        [$normalisedDefinitions, $errors] = $this->definitionsNormaliser->normalise($definitions);
        $this->reportErrors($errors, $ignoreWarnings);

        $this->reportErrors($this->referenceValidator->validate($normalisedDefinitions), $ignoreWarnings);

        return ["definitions" => $normalisedDefinitions, "namespaces" => $namespaces];
    }

}