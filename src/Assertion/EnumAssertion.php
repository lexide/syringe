<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\ErrorHelper;
use Pimple\Container;

class EnumAssertion implements AssertionInterface
{

    protected ErrorHelper $errorHelper;

    /**
     * @param ErrorHelper $errorHelper
     */
    public function __construct(ErrorHelper $errorHelper)
    {
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
            return [$this->errorHelper->assertionError(
                "The operand for an enum assertion must be an array; " . gettype($operand) . " found",
                ["reference" => $reference]
            )];
        }

        if ($type != self::TYPE_PARAMETER) {
            return [$this->errorHelper->assertionError(
                "The enum assertion can only be performed on parameters (that exist)",
                ["reference" => $reference]
            )];
        }

        $value = $container[$reference];

        if (is_array($value) || is_object($value)) {
            return [$this->errorHelper->assertionError(
                "The resolved parameter was not a scalar value",
                ["reference" => $reference]
            )];
        }

        if (!isset(array_flip($operand)[$value])) {
            return [$this->errorHelper->assertionError(
                "The resolved parameter was not in the list of allowed values",
                ["reference" => $reference]
            )];
        }

        return [];
    }

}