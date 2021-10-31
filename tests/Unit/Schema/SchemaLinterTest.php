<?php

namespace Lexide\Syringe\Test\Unit\Schema;

use Lexide\Syringe\Schema\SchemaLinter;
use Lexide\Syringe\Schema\SchemaLintError;
use PHPUnit\Framework\TestCase;

class SchemaLinterTest extends TestCase
{

    /**
     * @dataProvider schemaLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testSchemasFormat(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider typeLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testTypeLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider childrenLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testChildrenLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider elementLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testElementLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider requiredChildrenLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testRequiredChildrenLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider emptyLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testEmptyLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider warningLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testWarningLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @dataProvider oneOfLintProvider
     *
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    public function testOneOfLinting(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
    {
        $this->lintTester($schemas, $shouldPass, $expectedLintErrors);
    }

    /**
     * @param array $schemas
     * @param bool $shouldPass
     * @param array $expectedLintErrors
     */
    protected function lintTester(array $schemas, bool $shouldPass, array $expectedLintErrors = [])
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
                                if ($errorContext[$index] == $value) {
                                    ++$matched;
                                }
                            }
                        }
                        if ($required == $matched) {
                            continue 2;
                        }
                    }
                    $this->fail("The expected error " . json_encode($expectedError) . " was not found");
                }
            }
        }

    }

    public function schemaLintProvider(): array
    {
        return [
            "invalid schemas array" => [
                ["dud" => "fail"],
                false,
                [
                    ["search" => "/schemas/"]
                ]
            ],
            "No schemas to lint" => [
                ["schemas" => []],
                false,
                [
                    ["search" => "/schemas/"]
                ]
            ],
            "One empty schema" => [
                ["schemas" => ["I'm empty" => []]],
                false,
                [
                    ["search" => "/is empty/", "context" => [0 => "I'm empty"]]
                ]
            ],
            "Schema has no type" => [
                ["schemas" => ["noType" => ["something" => "else"]]],
                false,
                [
                    ["search" => "/no.*type/", "context" => [0 => "noType"]]
                ]
            ]
        ];
    }

    public function typeLintProvider(): array
    {
        return [
            "Type not a string or list" => [
                ["schemas" => ["one" => ["type" => 123]]],
                false,
                [
                    ["search" => "/not.*string.*list/", "context" => [0 => "type"]]
                ]
            ],
            "Type is a string" => [
                ["schemas" => ["one" => ["type" => "string"]]],
                true
            ],
            "Type is a single element list" => [
                ["schemas" => ["one" => ["type" => ["number"]]]],
                true
            ],
            "Type is a multi element list" => [
                ["schemas" => ["one" => ["type" => ["number", "object", "list"]]]],
                true
            ],
            "Type is an invalid string" => [
                ["schemas" => ["one" => ["type" => "badValue"]]],
                false,
                [
                    ["search" => "/not.*valid type/", "context" => [0 => "badValue", 1 => "type"]]
                ]
            ],
            "Type list contains invalid types" => [
                ["schemas" => ["one" => ["type" => ["number", "bad1", "serviceReference", "bad2", "class"]]]],
                false,
                [
                    ["search" => "/not.*valid type/", "context" => [0 => "bad1", 1 => "type"]],
                    ["search" => "/not.*valid type/", "context" => [0 => "bad2", 1 => "type"]]
                ]
            ],
            "Type references schema" => [
                [
                    "schemas" => [
                        "one" => ["type" => "@two"],
                        "two" => ["type" => "string"]
                    ]
                ],
                true
            ],
            "Type references missing schema" => [
                [
                    "schemas" => [
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

    public function childrenLintProvider(): array
    {
        return [
            "children is not an array" => [
                ["schemas" => ["one" => ["type" => "object", "children" => "invalid"]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "children"]]
                ]
            ],
            "children is a numeric array" => [
                ["schemas" => ["one" => ["type" => "object", "children" => ["shouldn't", "be", "numeric"]]]],
                false,
                [
                    ["search" => "/numeric keys/", "context" => [0 => "children"]]
                ]
            ],
            "children is an empty array" => [
                ["schemas" => ["one" => ["type" => "object", "children" => []]]],
                false,
                [
                    ["search" => "/not.*empty/", "context" => [0 => "children"]]
                ]
            ],
            "child element is not an array" => [
                ["schemas" => ["one" => ["type" => "object", "children" => ["badChild" => "badValue"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "badChild"]]
                ]
            ],
            "child element is not a schema" => [
                ["schemas" => ["one" => ["type" => "object", "children" => ["badChild" => ["bad" => "value"]]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "badChild"]]
                ]
            ],
            "child element is a valid schema" => [
                ["schemas" => ["one" => ["type" => "object", "children" => ["two" => ["type" => "string"]]]]],
                true
            ],
            "child element errors refer to the child" => [
                ["schemas" => ["one" => ["type" => "object", "children" => ["two" => ["type" => "object", "children" => "badValue"]]]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "children", 1 => "one.two"]]
                ]
            ]
        ];
    }

    public function elementLintProvider(): array
    {
        return [
            "element is not an array" => [
                ["schemas" => ["one" => ["type" => "object", "element" => "badValue"]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element"]]
                ]
            ],
            "element is not a schema" => [
                ["schemas" => ["one" => ["type" => "object", "element" => ["bad" => "value"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element"]]
                ]
            ],
            "element is a valid schema" => [
                ["schemas" => ["one" => ["type" => "object", "element" => ["type" => "string"]]]],
                true
            ],
            "element schema errors refer to the element" => [
                ["schemas" => ["one" => ["type" => "object", "element" => ["type" => "object", "element" => "badValue"]]]],
                false,
                [
                    ["search" => "/not a schema/", "context" => [0 => "element", 1 => "one.element"]]
                ]
            ]
        ];
    }

    public function requiredChildrenLintProvider(): array
    {
        return [
            "requiredChildren is not an array" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => "badValue"]]],
                false,
                [
                    ["search" => "/not an array/", "context" => [0 => "requiredChildren"]]
                ]
            ],
            "requiredChildren is an empty array" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => []]]],
                false,
                [
                    ["search" => "/not.*empty/", "context" => [0 => "requiredChildren"]]
                ]
            ],
            "children directive is missing" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["missingChild"]]]],
                false,
                [
                    ["search" => "/is set.*doesn't exist/", "context" => [0 => "requiredChildren", 2 => "children"]]
                ]
            ],
            "required child is missing" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["missingChild" => true], "children" => ["not this one" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not defined/", "context" => [0 => "missingChild", 2 => "children"]]
                ]
            ],
            "requirement is 'true'" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => true], "children" => ["child" => ["type" => "string"]]]]],
                true
            ],
            "requirement is 'false'" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => false], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*boolean true/", "context" => [0 => "child"]]
                ]
            ],
            "requirement is not an array or boolean" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => "invalid"], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*boolean true.* array/", "context" => [0 => "child"]]
                ]
            ],
            "invalid requirement" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["something" => "else"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/unexpected requirement/i", "context" => [0 => "something", 1 => "child"]]
                ]
            ],
            "'if' requirement is not a string or list of strings" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["if" => 123]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*string.*list of strings/", "context" => [0 => "if", 1 => "child"]]
                ]
            ],
            "'if' requirement refers to a missing dependant child" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["if" => "notFound"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/refer to.*not defined/", "context" => [0 => "child", 2 => "notFound"]]
                ]
            ],
            "'if' is a valid string requirement" => [
                [
                    "schemas" => [
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
                    "schemas" => [
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
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["ifNot" => 123]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/not.*string.*list of strings/", "context" => [0 => "ifNot", 1 => "child"]]
                ]
            ],
            "'ifNot' requirement refers to a missing dependant child" => [
                ["schemas" => ["one" => ["type" => "object", "requiredChildren" => ["child" => ["ifNot" => "notFound"]], "children" => ["child" => ["type" => "string"]]]]],
                false,
                [
                    ["search" => "/refer to.*not defined/", "context" => [0 => "child", 2 => "notFound"]]
                ]
            ],
            "'ifNot' is a valid string requirement" => [
                [
                    "schemas" => [
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
                    "schemas" => [
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

    public function emptyLintProvider(): array
    {
        return [
            "empty is 'true'" => [
                ["schemas" => ["one" => ["type" => "object", "empty" => true]]],
                true
            ],
            "empty is 'false'" => [
                ["schemas" => ["one" => ["type" => "object", "empty" => false]]],
                true
            ],
            "empty is not a boolean" => [
                ["schemas" => ["one" => ["type" => "object", "empty" => "true"]]],
                false,
                [
                    ["search" => "/not.*boolean/", "context" => [0 => "empty"]]
                ]
            ],
        ];
    }

    public function warningLintProvider(): array
    {
        return [
            "warning is not a string" => [
                ["schemas" => ["one" => ["type" => "object", "warning" => ["blah"]]]],
                false,
                [
                    ["search" => "/not.*string/", "context" => [0 => "warning"]]
                ]
            ],
            "warning is a string" => [
                ["schemas" => ["one" => ["type" => "object", "warning" => "I'm a warning"]]],
                true
            ]
        ];
    }

    public function oneOfLintProvider(): array
    {
        return [
            "oneOf is not an array" => [
                ["schemas" => ["one" => ["type" => "object", "oneOf" => "badValue"]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is not an array of arrays" => [
                ["schemas" => ["one" => ["type" => "object", "oneOf" => ["badValue"]]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is not an array of schemas" => [
                ["schemas" => ["one" => ["type" => "object", "oneOf" => [["badKey" => "badValue"]]]]],
                false,
                [
                    ["search" => "/not.*list.*schemas/", "context" => [0 => "oneOf"]]
                ]
            ],
            "oneOf is an array of schemas" => [
                [
                    "schemas" => [
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
                    "schemas" => [
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
