<?php

namespace Lexide\Syringe\Compiler;

use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Validation\ValidationError;

class CompilationHelper
{

    public function isServiceReference(string $value): bool
    {
        return strlen($value) > 1 && $value[0] === ContainerBuilder::SERVICE_CHAR;
    }

    /**
     * @param string $serviceReference
     * @return string
     */
    public function getServiceKey(string $serviceReference): string
    {
        return ltrim($serviceReference, ContainerBuilder::SERVICE_CHAR);
    }

    /**
     * @param string $serviceKey
     * @return string
     */
    public function getServiceReference(string $serviceKey): string
    {
        // run getServiceKey to make sure that we don't add the service character when it already exists
        return ContainerBuilder::SERVICE_CHAR . $this->getServiceKey($serviceKey);
    }

    public function findNextParameter(string $string, int $offset = 0): ?string
    {
        return $this->findNextEmbeddedReference($string, ContainerBuilder::PARAMETER_CHAR, $offset);
    }

    public function findNextConstant(string $string, int $offset = 0): ?string
    {
        return $this->findNextEmbeddedReference($string, ContainerBuilder::CONSTANT_CHAR, $offset);
    }

    public function replaceParameterReference(string $string, string $parameter, string $replacement, bool $removeChars = false)
    {
        return $this->replaceEmbeddedReference($string, $parameter, ContainerBuilder::PARAMETER_CHAR, $replacement, $removeChars);
    }

    public function replaceConstantReference(string $string, string $parameter, string $replacement, bool $removeChars = false)
    {
        return $this->replaceEmbeddedReference($string, $parameter, ContainerBuilder::CONSTANT_CHAR, $replacement, $removeChars);
    }

    protected function findNextEmbeddedReference(string $string, string $char, int $offset): ?string
    {
        if ($offset > 0) {
            $string = substr($string, $offset);
        }

        if (preg_match($this->getEmbeddedReferenceRegex(preg_quote($char)), $string, $matches)) {
            return trim($matches[0], $char);
        }
        return null;
    }

    protected function replaceEmbeddedReference(string $string, string $reference, string $char, string $replacement, bool $removeChars)
    {
        $pattern = "/" . preg_quote($char . $reference . $char) . "/u";
        if (!$removeChars) {
            $replacement = $char . $replacement . $char;
        }

        return preg_replace($pattern, $replacement, $string, 1);
    }

    protected function getEmbeddedReferenceRegex(string $char): string
    {
        return "/(?<!$char){$char}[^$char]+$char/u";
    }

    /**
     * @param string $message
     * @param array $context
     * @return ValidationError
     */
    public function syntaxError(string $message, array $context = []): ValidationError
    {
        return new ValidationError("syntax", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return ValidationError
     */
    public function normalisationError(string $message, array $context = []): ValidationError
    {
        return new ValidationError("normalisation", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return ValidationError
     */
    public function referenceError(string $message, array $context = []): ValidationError
    {
        return new ValidationError("reference", $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return ValidationError
     */
    public function warning(string $message, array $context = []): ValidationError
    {
        return new ValidationError("warning", $message, $context);
    }

}