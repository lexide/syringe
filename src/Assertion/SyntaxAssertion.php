<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Schema\SchemaLinter;
use Lexide\Syringe\Validation\SyntaxValidatorFactory;
use Pimple\Container;

class SyntaxAssertion implements AssertionInterface
{

    protected SchemaLinter $schemaLinter;
    protected SyntaxValidatorFactory $syntaxValidatorFactory;
    protected ErrorHelper $errorHelper;

    /**
     * @param SchemaLinter $schemaLinter
     * @param SyntaxValidatorFactory $syntaxValidatorFactory
     * @param ErrorHelper $errorHelper
     */
    public function __construct(
        SchemaLinter $schemaLinter,
        SyntaxValidatorFactory $syntaxValidatorFactory,
        ErrorHelper $errorHelper,
    ) {
        $this->schemaLinter = $schemaLinter;
        $this->syntaxValidatorFactory = $syntaxValidatorFactory;
        $this->errorHelper = $errorHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function assert(
        array|string|null $operand,
        string $reference,
        ?string $type,
        string|int|bool|array|null $definition,
        Container $container
    ): array {
        if (!is_array($operand)) {
            return [$this->errorHelper->assertionError("Invalid schemata definition", ["reference" => $reference])];
        }

        $schemata = ["schemata" => ["syringe" => $operand]];

        $errors = $this->schemaLinter->lint($schemata);
        if (!empty($errors)) {
            return $errors;
        }

        if ($type != self::TYPE_PARAMETER) {
            return [$this->errorHelper->assertionError(
                "The syntax assertion can only be performed on parameters (that exist)",
                ["reference" => $reference]
            )];
        }

        $value = $container[$reference];

        if (!is_array($value)) {
            return [$this->errorHelper->assertionError(
                "The resolved parameter was not a data structure array",
                ["reference" => $reference]
            )];
        }

        $syntaxValidator = $this->syntaxValidatorFactory->create($schemata);

        return array_map(
            function(SyringeError $error) use ($reference) {
                $error->addContext("reference", $reference);
                return $error;
            },
            $syntaxValidator->validate($value, "assertions")
        );

    }

}