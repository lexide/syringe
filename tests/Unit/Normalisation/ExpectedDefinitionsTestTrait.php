<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

/**
 * @method fail($message);
 * @method assertSame($expected, $actual, $message);
 */
trait ExpectedDefinitionsTestTrait
{

    /**
     * @param array $definitions
     * @param array $expectedDefinitions
     */
    protected function testExpectedDefinitions(array $definitions, array $expectedDefinitions)
    {
        foreach ($expectedDefinitions as $path => $expectedDefinition) {
            $actualDefinition = $definitions;
            $parts = explode(">", $path);
            foreach ($parts as $part) {
                if (!isset($actualDefinition[$part])) {
                    $this->fail("Could not find the path '$path' in the definitions. '$part' was missing");
                }
                $actualDefinition = $actualDefinition[$part];
            }
            $this->assertSame(
                $expectedDefinition,
                $actualDefinition,
                "The definition for '$path' is not what we expect: " . json_encode($actualDefinition)
            );
        }
    }

    /**
     * @param array $definitions
     * @param array $missingDefinitions
     */
    protected function testMissingDefinitions(array $definitions, array $missingDefinitions)
    {
        foreach ($missingDefinitions as $path) {
            $actualDefinition = $definitions;
            $parts = explode(">", $path);
            foreach ($parts as $part) {
                if (!isset($actualDefinition[$part])) {
                    // definition is missing. Move onto the next path
                    continue 2;
                }
                $actualDefinition = $actualDefinition[$part];
            }
            $this->fail("The path '$path' was present when it was meant to be missing from the definitions.");
        }
    }

}