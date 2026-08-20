<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Error\SyringeError;
use Pimple\Container;

interface AssertionInterface
{

    public const TYPE_PARAMETER = "parameter";
    public const TYPE_SERVICE = "service";

    /**
     * @param string|array|null $operand
     * @param string $reference
     * @param ?string $type
     * @param string|int|bool|array|null $definition
     * @param Container $container
     * @return SyringeError[]
     */
    public function assert(
        array|string|null $operand,
        string $reference,
        ?string $type,
        string|int|bool|array|null $definition,
        Container $container
    ): array;

}