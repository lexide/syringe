<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\ErrorHelper;
use Pimple\Container;

class NotEmptyAssertion implements AssertionInterface
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
        $errors = [];
        switch ($type) {
            case self::TYPE_PARAMETER:
                if (empty($definition)) {
                    $errors[] = $this->errorHelper->assertionError(
                        "$reference is defined as an empty value",
                        ["reference", $reference]
                    );
                } elseif (empty($container[$reference])) {
                    $errors[] = $this->errorHelper->assertionError(
                        "$reference resolves to an empty value",
                        ["reference", $reference]
                    );
                }
                break;
            case self::TYPE_SERVICE:
                if (($definition["stub"] ?? false) === true) {
                    $errors[] = $this->errorHelper->assertionError(
                        "$reference is a stub service and considered empty",
                        ["reference", $reference]
                    );
                }
                break;
            default:
                $errors[] = $this->errorHelper->assertionError(
                    "$reference is not defined",
                    ["reference", $reference]
                );
                break;
        }
        return $errors;
    }

}