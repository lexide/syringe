<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\Validation\ValidationError;
use Mockery\MockInterface;

/**
 * @method fail($message);
 */
trait NormalisationErrorTestTrait
{

    /**
     * @var CompilationHelper|MockInterface
     */
    protected $helper;

    /**
     * @var ValidationError|MockInterface
     */
    protected $error;

    protected function setupErrorMocks()
    {
        $this->helper = \Mockery::mock(CompilationHelper::class);
        $this->error = \Mockery::mock(ValidationError::class);
    }

    /**
     * @param array $expectedErrors - passed by-reference
     */
    protected function configureErrorTests(array &$expectedErrors)
    {
        $this->helper->shouldReceive("normalisationError")->andReturnUsing(function ($message) use (&$expectedErrors) {
            foreach ($expectedErrors as $i => $expectedErrorRegex) {
                if (preg_match($expectedErrorRegex, $message)) {
                    unset($expectedErrors[$i]);
                    return $this->error;
                }
            }
            $this->fail("An unexpected error was raised '$message'");
            return null;
        });
    }

}