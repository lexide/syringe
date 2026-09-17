<?php

namespace Lexide\Syringe\Error;

class ErrorHelper
{

    /**
     * @param string $message
     * @param array $context
     * @return SyringeError
     */
    public function syntaxError(string $message, array $context = []): SyringeError
    {
        return new SyringeError("syntax", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return SyringeError
     */
    public function normalisationError(string $message, array $context = []): SyringeError
    {
        return new SyringeError("normalisation", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return SyringeError
     */
    public function referenceError(string $message, array $context = []): SyringeError
    {
        return new SyringeError("reference", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return SyringeError
     */
    public function assertionError(string $message, array $context = []): SyringeError
    {
        return new SyringeError("assertion", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return SyringeError
     */
    public function warning(string $message, array $context = []): SyringeError
    {
        return new SyringeError("warning", $message, $context);
    }

}