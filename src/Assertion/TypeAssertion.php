<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Validation\TypeValidator;
use Pimple\Container;

class TypeAssertion implements AssertionInterface
{

    protected const TYPES = [
        "int",
        "integer",
        "bool",
        "boolean",
        "float",
        "double",
        "number",
        "string",
        "array",
        "object",
        "list",
        "null",
        "any"
    ];

    protected TypeValidator $typeValidator;
    protected ErrorHelper $errorHelper;

    /**
     * @param TypeValidator $typeValidator
     * @param ErrorHelper $errorHelper
     */
    public function __construct(TypeValidator $typeValidator, ErrorHelper $errorHelper)
    {
        $this->typeValidator = $typeValidator;
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

        if (!in_array($operand, self::TYPES)) {
            return [$this->errorHelper->assertionError(
                "Invalid type to check for: $operand",
                ["reference" => $reference]
            )];
        }

        switch ($type) {
            case self::TYPE_PARAMETER:
                if (!$this->typeValidator->checkType($operand, $container[$reference])) {
                    return [$this->errorHelper->assertionError(
                        "The resolved parameter's type was not '$operand'",
                        ["reference" => $reference]
                    )];
                }
                break;
            case self::TYPE_SERVICE:
                return [$this->errorHelper->assertionError(
                    "Cannot perform a type assertion on a service reference",
                    ["reference" => $reference]
                )];
            default:
                if (!in_array($operand, ["null", "any"])) {
                    return [$this->errorHelper->assertionError(
                        "Cannot perform a type assertion on a reference that does not exist",
                        ["reference" => $reference]
                    )];
                }
                break;
        }

        return [];
    }

}