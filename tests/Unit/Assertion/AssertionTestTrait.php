<?php

namespace Lexide\Syringe\Test\Unit\Assertion;

use Lexide\Syringe\Error\SyringeError;

/**
 * @method assertCount($count, $array)
 * @method assertMatchesRegularExpression($pattern, $string)
 */
trait AssertionTestTrait
{

    /**
     * @param SyringeError[] $errors
     * @param string[] $regexes
     */
    protected function checkMessages(array $errors, array $regexes): void
    {
        $this->assertCount(count($regexes), $errors);
        foreach ($errors as $i => $error) {
            $this->assertMatchesRegularExpression($regexes[$i], $error->getMessage());
        }
    }

}