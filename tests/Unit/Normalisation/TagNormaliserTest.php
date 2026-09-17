<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Normalisation\TagNormaliser;
use PHPUnit\Framework\TestCase;

class TagNormaliserTest extends TestCase
{
    use ExpectedDefinitionsTestTrait;

    public function testStringTagsAreNormalised()
    {
        $definitions = [
            "services" => [
                "one" => [
                    "tags" => [
                        "foo"
                    ]
                ]
            ]
        ];

        $normaliser = new TagNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, [
            "services>one>tags>0>tag" => "foo"
        ]);
    }

    public function testNamedTagsAreNormalised()
    {
        $definitions = [
            "services" => [
                "one" => [
                    "tags" => [
                        "foo" => "one",
                        "bar" => 2
                    ]
                ]
            ]
        ];

        $normaliser = new TagNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, [
            "services>one>tags>0>tag" => "foo",
            "services>one>tags>0>key" => "one",
            "services>one>tags>1>tag" => "bar",
            "services>one>tags>1>order" => 2
        ]);

        $this->testMissingDefinitions($normalisedDefinitions, [
            "services>one>tags>foo",
            "services>one>tags>bar"
        ]);
    }

    public function testCorrectlyFormattedTagsAreNotNormalised()
    {
        $definitions = [
            "services" => [
                "one" => [
                    "tags" => [
                        ["tag" => "foo"],
                        ["tag" => "bar", "order" => 2],
                        ["tag" => "baz", "key" => "three"]
                    ]
                ]
            ]
        ];

        $normaliser = new TagNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, [
            "services>one>tags>0>tag" => "foo",
            "services>one>tags>1>tag" => "bar",
            "services>one>tags>1>order" => 2,
            "services>one>tags>2>tag" => "baz",
            "services>one>tags>2>key" => "three"
        ]);

        $this->testMissingDefinitions($normalisedDefinitions, [
            "services>one>tags>0>order",
            "services>one>tags>0>key",
            "services>one>tags>1>key",
            "services>one>tags>2>order"
        ]);
    }

    public function testAllTagFormatsAreNormalised()
    {
        $definitions = [
            "services" => [
                "one" => [
                    "tags" => [
                        "foo",
                        "bar" => 2,
                        ["tag" => "baz", "key" => "three"]
                    ]
                ]
            ]
        ];

        $normaliser = new TagNormaliser();
        $normalisedDefinitions = $normaliser->normalise($definitions);

        $this->testExpectedDefinitions($normalisedDefinitions, [
            "services>one>tags>0>tag" => "foo",
            "services>one>tags>1>tag" => "bar",
            "services>one>tags>1>order" => 2,
            "services>one>tags>2>tag" => "baz",
            "services>one>tags>2>key" => "three"
        ]);

        $this->testMissingDefinitions($normalisedDefinitions, [
            "services>one>tags>0>order",
            "services>one>tags>0>key",
            "services>one>tags>1>key",
            "services>one>tags>2>order"
        ]);
    }

}
