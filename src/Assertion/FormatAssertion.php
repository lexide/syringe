<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\ErrorHelper;
use Pimple\Container;

class FormatAssertion implements AssertionInterface
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
        if (!is_string($operand) || substr($operand, 0, 1) != substr($operand, -1)) {
            return [$this->errorHelper->assertionError("Invalid regex expression", ["reference" => $reference])];
        }

        if ($type != self::TYPE_PARAMETER) {
            return [$this->errorHelper->assertionError(
                "The format assertion can only be performed on parameters (that exist)",
                ["reference" => $reference]
            )];
        }

        $value = $container[$reference];
        if (!is_string($value)) {
            return [$this->errorHelper->assertionError(
                "The parameter resolved to a value that isn't a string, so it's format cannot be checked",
                ["reference" => $reference]
            )];
        }

        if (!preg_match($operand, $value)) {
            return [$this->errorHelper->assertionError(
                "The resolved parameter did not match the format $operand",
                ["reference" => $reference]
            )];
        }

        return [];
    }

}