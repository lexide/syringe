<?php

namespace Lexide\Syringe\Test\Unit\Normalisation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Mockery\MockInterface;

/**
 * @method fail($message);
 */
trait NormalisationErrorTestTrait
{

    protected ErrorHelper|MockInterface $errorHelper;
    protected SyringeError|MockInterface $error;

    protected function setupErrorMocks(): void
    {
        $this->errorHelper = \Mockery::mock(ErrorHelper::class);
        $this->error = \Mockery::mock(SyringeError::class);
    }

    /**
     * @param array $expectedErrors - passed by-reference
     */
    protected function configureErrorTests(array &$expectedErrors): void
    {
        $this->errorHelper->shouldReceive("normalisationError")->andReturnUsing(function ($message) use (&$expectedErrors) {
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