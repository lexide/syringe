<?php

namespace Lexide\Syringe\Test\Unit\Validation;

use Lexide\Syringe\Validation\TypeValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TypeValidatorTest extends TestCase
{

    #[DataProvider("typeProvider")]
    public function testTypeValidation(string $type, mixed $definition, bool $expectedResult)
    {
        $validator = new TypeValidator();

        $this->assertSame($expectedResult, $validator->checkType($type, $definition));
    }

    public static function typeProvider(): array
    {
        return [
            "array type (mixed)" => [
                "array",
                [
                    "one",
                    "two" => 2
                ],
                true
            ],
            "array type (list)" => [
                "array",
                [
                    "one",
                    "two"
                ],
                true
            ],
            "array type (object)" => [
                "array",
                [
                    "one" => 1,
                    "two" => 2
                ],
                true
            ],
            "list type" => [
                "list",
                [
                    "one",
                    "two"
                ],
                true
            ],
            "object type" => [
                "object",
                [
                    "one" => 1,
                    "two" => 2
                ],
                true
            ],
            "string type" => [
                "string",
                "string",
                true
            ],
            "number type (integer)" => [
                "number",
                123,
                true
            ],
            "number type (float)" => [
                "number",
                0.123,
                true
            ],
            "bool type" => [
                "bool",
                true,
                true
            ],
            "any type (object)" => [
                "any",
                new \stdClass(),
                true
            ],
            "any type (scalar)" => [
                "any",
                12345,
                true
            ],
            "any type (array)" => [
                "any",
                ["foo"],
                true
            ],
            "null type" => [
                "null",
                null,
                true
            ],
            "string type failure" => [
                "string",
                12345,
                false
            ],
            "number type failure" => [
                "number",
                "12345",
                false

            ],
            "bool type failure" => [
                "bool",
                12345,
                false
            ],
            "null type failure" => [
                "null",
                "not null",
                false
            ],
            "list type failure (object)" => [
                "list",
                [
                    "one" => 1,
                    "two" => 2
                ],
                false
            ],
            "list type failure (array)" => [
                "list",
                [
                    "one",
                    "two" => 2
                ],
                false
            ],
            "object type failure (list)" => [
                "object",
                [
                    "one",
                    "two"
                ],
                false
            ],
            "object type failure (array)" => [
                "object",
                [
                    "one",
                    "two" => 2
                ],
                false
            ]
        ];
    }

}
