<?php

namespace Lexide\Syringe\Test\Unit\Schema;

use Lexide\Syringe\Schema\SchemaLinter;
use Lexide\Syringe\Schema\SchemaLintError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemaLinterTest extends TestCase
{

    #[DataProvider("schemaLintProvider")]
    public function testSchemasFormat(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("typeLintProvider")]
    public function testTypeLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("childrenLintProvider")]
    public function testChildrenLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("elementLintProvider")]
    public function testElementLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("requiredChildrenLintProvider")]
    public function testRequiredChildrenLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("emptyLintProvider")]
    public function testEmptyLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("warningLintProvider")]
    public function testWarningLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    #[DataProvider("oneOfLintProvider")]
    public function testOneOfLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    protected function lintTester(array $schemas, bool $shouldPass, array $expectedLintErrors = []): void
    {
        $linter = new SchemaLinter();

        $errors = $linter->lint($schemas);

        $this->assertEmpty(
            array_filter($errors, function($error) {
                return !$error instanceof SchemaLintError;
            }),
            "The linted error array contains elements that aren't instances of SchemaLintError"
        );

        if ($shouldPass) {
            $this->assertEmpty($errors, "There were lint errors when none were expected");
        } else {
            $this->assertNotEmpty($errors, "No errors were found when they were expected");
            if (!empty($expectedLintErrors)) {
                foreach ($expectedLintErrors as $expectedError) {
                    foreach ($errors as $error) {
                        $required = 0;
                        $matched = 0;
                        if (!empty($expectedError["search"])) {
                            ++$required;
                            if (preg_match($expectedError["search"], $error->getMessage())) {
                                ++$matched;
                            }
                        }
                        if (!empty($expectedError["context"])) {
                            $errorContext = $error->getReplacements();
                            foreach ($expectedError["context"] as $index => $value) {
                                ++$required;
                                if (($errorContext[$index] ?? null) == $value) {
                                    ++$matched;
                                }
                            }
                        }
                        if ($required == $matched) {
                            continue 2;
                        }
                    }
                    echo "expected: " . json_encode($expectedError) . "\n" . print_r($errors, true) . "\n";
                    $this->fail("The expected error " . json_encode($expectedError) . " was not found");
                }
            }
        }

    }

    public static function schemaLintProvider(): array
    {
        return [
            "invalid schemas array" => [
                ["dud" => "fail"],
                false,
                [
                    ["search" => "/schemata/"]
                ]
            ],
            "No schemas to lint" => [
                ["schemata" => []],
                false,
                [
                    ["search" => "/schemata/"]
                ]
            ],
            "One empty schema" => [
                ["schemata" => ["I'm empty" => []]],
                false,
                [
                    ["search" => "/is empty/", "context" => [0 => "I'm empty"]]
                ]
            ],
            "Schema has no type" => [
                ["schemata" => ["noType" => ["something" => "else"]]],
                false,
                [
                    ["search" => "/requires.*type/", "context" => [0 => "noType"]]
                ]
            ]
        ];
    }

    public static function typeLintProvider(): array
    {
        return [
            "Type not a string or list" => [
                ["schemata" => ["one" => ["type" => 123]]],
                false,
                [
                    ["search" => "/not.*string.*list/", "context" => [0 => "type"]]
                ]
            ],
            "Type is a string" => [
                ["schemata" => ["one" => ["type" => "string"]]],
                true
            ],
            "Type is a single element list" => [
                ["schemata" => ["one" => ["type" => ["number"]]]],
                true
            ],
            "Type is a multi element list" => [
                ["schemata" => ["one" => ["type" => ["number", "object", "list"]]]],
                true
            ],
            "Type is an invalid string" => [
                ["schemata" => ["one" => ["type" => "badValue"]]],
                false,
                [
                    ["search" => "/not.*valid type/", "context" => [0 => "badValue", 1 => "type"]]
                ]
            ],
            "Type list contains invalid types" => [
                ["schemata" => ["one" => ["type" => ["number", "bad1", "serviceReference", "bad2", "class"]]]],
                false,
                [
                    ["search" => "/not.*valid type/", "context" => [0 => "bad1", 1 => "type"]],
                    ["search" => "/not.*valid type/", "context" => [0 => "bad2", 1 => "type"]]
                ]
            ],
            "Type references schema" => [
                [
                    "schemata" => [
                        "one" => ["type" => "@two"],
                        "two" => ["type" => "string"]
                    ]
                ],
                true
            ],
            "Type references missing schema" => [
                [
                    "schemata" => [
                        "one" => ["type" => "@three"],
                        "two" => ["type" => "string"]
                    ]
                ],
                false,
                [
                    ["search" => "/refers.*schema.*doesn't exist/", "context" => [0 => "type", 2 => "three"]]
                ]
            ]
        ];
    }

    public static function childrenLintProvider(): array
    {
        return [
            "children is not an array" => [
                ["schemata" => ["one" => ["type" => "object", "children" => "invalid"]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "children"]]
                ]
            ],
            "children is a numeric array" => [
                ["schemata" => ["one" => ["type" => "object", "children" => ["shouldn't", "be", "numeric"]]]],
                false,
                [
                    ["search" => "/numeric keys/", "context" => [0 => "children"]]
                ]
            ],
            "children is an empty array" => [
                ["schemata" => ["one" => ["type" => "object", "children" => []]]],
                false,
                [
                    ["search" => "/not.*empty/", "context" => [0 => "children"]]
                ]
            ],
            "child element is not an array" => [
                ["schemata" => ["one" => ["type" => "object", "children" => ["badChild" => "badValue"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "badChild"]]
                ]
            ],
            "child element is not a schema" => [
                ["schemata" => ["one" => ["type" => "object", "children" => ["badChild" => ["bad" => "value"]]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "badChild"]]
                ]
            ],
            "child element is a valid schema" => [
                ["schemata" => ["one" => ["type" => "object", "children" => ["two" => ["type" => "string"]]]]],
                true
            ],
            "child element errors refer to the child" => [
                ["schemata" => ["one" => ["type" => "object", "children" => ["two" => ["type" => "object", "children" => "badValue"]]]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "children", 1 => "one.two"]]
                ]
            ]
        ];
    }

    public static function elementLintProvider(): array
    {
        return [
            "element is not an array" => [
                ["schemata" => ["one" => ["type" => "object", "element" => "badValue"]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element"]]
                ]
            ],
            "element is not a schema" => [
                ["schemata" => ["one" => ["type" => "object", "element" => ["bad" => "value"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element"]]
                ]
            ],
            "element is a valid schema" => [
                ["schemata" => ["one" => ["type" => "object", "element" => ["type" => "string"]]]],
                true
            ],
            "element schema errors refer to the element" => [
                ["schemata" => ["one" => ["type" => "object", "element" => ["type" => "object", "element" => "badValue"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element", 1 => "one.element"]]
                ]
            ]
        ];
    }

    public static function requiredChildrenLintProvider(): array
    {
        return [
            "requiredChildren is not an array" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => "badValue"]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "requiredChildren"]]
                ]
            ],
            "requiredChildren is an empty array" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => []]]],
                false,
                [
                    ["search" => "/not.*empty/", "context" => [0 => "requiredChildren"]]
                ]
            ],
            "children directive is missing" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["missingChild"]]]],
                false,
                [
                    ["search" => "/is set.*doesn't exist/", "context" => [0 => "requiredChildren", 2 => "children"]]
                ]
            ],
            "required child is missing" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["missingChild" => true], "children" => ["not this one" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not defined/", "context" => [0 => "missingChild", 2 => "children"]]
                ]
            ],
            "requirement is 'true'" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => true], "children" => ["child" => ["type" => "string"]]]]],
                true
            ],
            "requirement is 'false'" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => false], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*boolean true/", "context" => [0 => "child"]]
                ]
            ],
            "requirement is not an array or boolean" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => "invalid"], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*boolean true.* array/", "context" => [0 => "child"]]
                ]
            ],
            "invalid requirement" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["something" => "else"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/unexpected requirement/i", "context" => [0 => "something", 1 => "child"]]
                ]
            ],
            "'if' requirement is not a string or list of strings" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["if" => 123]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*string.*list of strings/", "context" => [0 => "if", 1 => "child"]]
                ]
            ],
            "'if' requirement refers to a missing dependant child" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["if" => "notFound"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/refer to.*not defined/", "context" => [0 => "child", 2 => "notFound"]]
                ]
            ],
            "'if' is a valid string requirement" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "requiredChildren" => [
                                "child" => [
                                    "if" => "dependency"
                                ]
                            ],
                            "children" => [
                                "child" => [
                                    "type" => "string"
                                ],
                                "dependency" => [
                                    "type" => "string"
                                ]
                            ]
                        ]
                    ]
                ],
                true
            ],
            "'if' is a valid list of strings requirement" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "requiredChildren" => [
                                "child" => [
                                    "if" => [
                                        "dependency1",
                                        "dependency2",
                                        "dependency3"
                                    ]
                                ]
                            ],
                            "children" => [
                                "child" => [
                                    "type" => "string"
                                ],
                                "dependency1" => [
                                    "type" => "string"
                                ],
                                "dependency2" => [
                                    "type" => "string"
                                ],
                                "dependency3" => [
                                    "type" => "string"
                                ]
                            ]
                        ]
                    ]
                ],
                true
            ],
            "'ifNot' requirement is not a string or list of strings" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["ifNot" => 123]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*string.*list of strings/", "context" => [0 => "ifNot", 1 => "child"]]
                ]
            ],
            "'ifNot' requirement refers to a missing dependant child" => [
                ["schemata" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["ifNot" => "notFound"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/refer to.*not defined/", "context" => [0 => "child", 2 => "notFound"]]
                ]
            ],
            "'ifNot' is a valid string requirement" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "requiredChildren" => [
                                "child" => [
                                    "ifNot" => "dependency"
                                ]
                            ],
                            "children" => [
                                "child" => [
                                    "type" => "string"
                                ],
                                "dependency" => [
                                    "type" => "string"
                                ]
                            ]
                        ]
                    ]
                ],
                true
            ],
            "'ifNot' is a valid list of strings requirement" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "requiredChildren" => [
                                "child" => [
                                    "ifNot" => [
                                        "dependency1",
                                        "dependency2",
                                        "dependency3"
                                    ]
                                ]
                            ],
                            "children" => [
                                "child" => [
                                    "type" => "string"
                                ],
                                "dependency1" => [
                                    "type" => "string"
                                ],
                                "dependency2" => [
                                    "type" => "string"
                                ],
                                "dependency3" => [
                                    "type" => "string"
                                ]
                            ]
                        ]
                    ]
                ],
                true
            ]
        ];
    }

    public static function emptyLintProvider(): array
    {
        return [
            "empty is 'true'" => [
                ["schemata" => ["one" => ["type" => "object", "empty" => true]]],
                true
            ],
            "empty is 'false'" => [
                ["schemata" => ["one" => ["type" => "object", "empty" => false]]],
                true
            ],
            "empty is not a boolean" => [
                ["schemata" => ["one" => ["type" => "object", "empty" => "true"]]],
                false,
                [
                    ["search" => "/not.*boolean/", "context" => [0 => "empty"]]
                ]
            ],
        ];
    }

    public static function warningLintProvider(): array
    {
        return [
            "warning is not a string" => [
                ["schemata" => ["one" => ["type" => "object", "warning" => ["blah"]]]],
                false,
                [
                    ["search" => "/not.*string/", "context" => [0 => "warning"]]
                ]
            ],
            "warning is a string" => [
                ["schemata" => ["one" => ["type" => "object", "warning" => "I'm a warning"]]],
                true
            ]
        ];
    }

    public static function oneOfLintProvider(): array
    {
        return [
            "oneOf is not an array" => [
                ["schemata" => ["one" => ["type" => "object", "oneOf" => "badValue"]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is not an array of arrays" => [
                ["schemata" => ["one" => ["type" => "object", "oneOf" => ["badValue"]]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is not an array of schemas" => [
                ["schemata" => ["one" => ["type" => "object", "oneOf" => [["badKey" => "badValue"]]]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is an array of schemas" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "oneOf" => [
                                [
                                    "type" => "string"
                                ],
                                [
                                    "type" => "object"
                                ]
                            ]
                        ]
                    ]
                ],
                true
            ],
            "oneOf schema errors refer to the schema index" => [
                [
                    "schemata" => [
                        "one" => [
                            "type" => "object",
                            "oneOf" => [
                                [
                                    "type" => "string"
                                ],
                                [
                                    "type" => "invalid"
                                ]
                            ]
                        ]
                    ]
                ],
                false,
                [
                    ["search" => "/not.*valid type/", "context" => [2 => "one.oneOf[1]"]]
                ]
            ]
        ];
    }

}
